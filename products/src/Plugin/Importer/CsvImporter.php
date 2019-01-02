<?php

namespace Drupal\products\Plugin\Importer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\products\Plugin\ImporterBase;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Product importer from a CSV format.
 *
 * @Importer(
 *   id = "csv",
 *   label = @Translation("CSV Importer")
 * )
 */
class CsvImporter extends ImporterBase {

  /**
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, ClientInterface $httpClient, StreamWrapperManagerInterface $streamWrapperManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entityTypeManager, $httpClient);
    $this->streamWrapperManager = $streamWrapperManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('http_client'),
      $container->get('stream_wrapper_manager')
    );
  }


  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getConfigurationForm(\Drupal\products\Entity\ImporterInterface $importer) {
    $form = [];
    $config = $importer->getPluginConfiguration();
    $form['file'] = [
      '#type' => 'managed_file',
      '#default_value' => isset($config['file']) ? $config['file'] : '',
      '#title' => $this->t('File'),
      '#description' => $this->t('The CSV file containing the product records.'),
      '#required' => TRUE,
      '#upload_location' => 'public://',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function import() {
    $products = $this->getData();
    if (!$products) {
      return FALSE;
    }

    foreach ($products as $product) {
      $this->persistProduct($product);
    }

    return TRUE;
  }

  /**
   * Loads the product data from the remote URL.
   *
   * @return array
   */
  private function getData() {
    /** @var \Drupal\products\Entity\ImporterInterface $importer_config */
    $importer_config = $this->configuration['config'];
    $config = $importer_config->getPluginConfiguration();
    $fids = isset($config['file']) ? $config['file'] : [];
    if (!$fids) {
      return NULL;
    }

    $fid = reset($fids);
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    $wrapper = $this->streamWrapperManager->getViaUri($file->getFileUri());
    if (!$wrapper) {
      return NULL;
    }

    $url = $wrapper->realpath();
    $spl = new \SplFileObject($url, 'r');
    $data = [];
    while (!$spl->eof()) {
      $data[] = $spl->fgetcsv();
    }

    $products = [];
    $header = [];
    foreach ($data as $key => $row) {
      if ($key == 0) {
        $header = $row;
        continue;
      }

      if ($row[0] == "") {
        continue;
      }

      $product = new \stdClass();
      foreach ($header as $header_key => $label) {
        $product->{$label} = $row[$header_key];
      }
      $products[] = $product;
    }

    return $products;
  }


  /**
   * Saves a Product entity from the remote data.
   *
   * @param \stdClass $data
   */
  private function persistProduct($data) {
    /** @var \Drupal\products\Entity\ImporterInterface $config */
    $config = $this->configuration['config'];

    $existing = $this->entityTypeManager->getStorage('product')->loadByProperties(['remote_id' => $data->id, 'source' => $config->getSource()]);
    if (!$existing) {
      $values = [
        'remote_id' => $data->id,
        'source' => $config->getSource(),
        'type' => $config->getBundle(),
      ];
      /** @var \Drupal\products\Entity\ProductInterface $product */
      $product = $this->entityTypeManager->getStorage('product')->create($values);
      $product->setName($data->name);
      $product->setProductNumber($data->number);
      $product->save();
      return;
    }

    if (!$config->updateExisting()) {
      return;
    }

    /** @var \Drupal\products\Entity\ProductInterface $product */
    $product = reset($existing);
    $product->setName($data->name);
    $product->setProductNumber($data->number);
    $product->save();
  }
}
