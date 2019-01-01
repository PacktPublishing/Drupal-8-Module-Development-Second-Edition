<?php

namespace Drupal\products\Commands;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\products\Plugin\ImporterManager;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputOption;

/**
 * Drush commands for products.
 */
class ProductCommands extends DrushCommands {

  /**
   * @var \Drupal\products\Plugin\ImporterManager
   */
  protected $importerManager;

  /**
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * ProductCommands constructor.
   *
   * @param \Drupal\products\Plugin\ImporterManager $importerManager
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   */
  public function __construct(ImporterManager $importerManager, LockBackendInterface $lock) {
    $this->importerManager = $importerManager;
    $this->lock = $lock;
  }

  /**
   * Imports the Products
   *
   * @option importer
   *   The importer config ID to use.
   *
   * @command products-import-run
   * @aliases pir
   *
   * @param array $options
   *   The command options.
   */
  public function import($options = ['importer' => InputOption::VALUE_OPTIONAL]) {
    $importer = $options['importer'];

    if (!is_null($importer)) {
      $plugin = $this->importerManager->createInstanceFromConfig($importer);
      if (is_null($plugin)) {
        $this->logger()->log('error', t('The specified importer does not exist.'));
        return;
      }

      $this->runPluginImport($plugin);
      return;
    }

    $plugins = $this->importerManager->createInstanceFromAllConfigs();
    if (!$plugins) {
      $this->logger()->log('error', t('There are no importers to run.'));
      return;
    }

    foreach ($plugins as $plugin) {
      $this->runPluginImport($plugin);
    }
  }

  /**
   * Runs an individual Importer plugin.
   *
   * @param \Drupal\products\Plugin\ImporterInterface $plugin
   */
  protected function runPluginImport(\Drupal\products\Plugin\ImporterInterface $plugin) {
    if (!$this->lock->acquire($plugin->getPluginId())) {
      $this->logger()->log('notice', t('The plugin @plugin is already running. Waiting for it to finish.', ['@plugin' => $plugin->getPluginDefinition()['label']]));
      if ($this->lock->wait($plugin->getPluginId())) {
        $this->logger()->log('notice', t('The wait is killing me. Giving up.'));
        return;
      }
    }

    $result = $plugin->import();
    $message_values = ['@importer' => $plugin->getConfig()->label()];
    if ($result) {
      $this->logger()->log('status', t('The "@importer" importer has been run.', $message_values));
      $this->lock->release($plugin->getPluginId());
      return;
    }

    $this->logger()->log('error', t('There was a problem running the "@importer" importer.', $message_values));
  }
}