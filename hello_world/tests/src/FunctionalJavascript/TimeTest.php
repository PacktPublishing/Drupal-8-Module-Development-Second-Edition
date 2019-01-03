<?php

namespace Drupal\Tests\hello_world\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Testing the simple Javascript timer on the Hello World page.
 *
 * @group hello_world
 */
class TimeTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['hello_world'];

  /**
   * Tests the time component.
   */
  public function testTime() {
    $this->drupalGet('/hello');
    $this->assertSession()->pageTextContains('The time is');

    $config = $this->config('hello_world.custom_salutation');
    $config->set('salutation', 'Testing salutation');
    $config->save();

    $this->drupalGet('/hello');
    $this->assertSession()->pageTextNotContains('The time is');
  }

}
