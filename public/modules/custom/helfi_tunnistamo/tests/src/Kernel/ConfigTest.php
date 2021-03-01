<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tunnistamo;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests Tunnistamo configuration.
 *
 * @group helfi_tunnistamo
 */
class ConfigTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['helfi_tunnistamo', 'openid_connect', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installConfig('helfi_tunnistamo');
  }

  /**
   * Make sure tunnistamo is enabled by default.
   */
  public function testEnable() : void {
    $config = $this->config('openid_connect.settings.tunnistamo');

    $this->assertNull($config->get('client_id'));
    $this->assertNull($config->get('client_secret'));
    $this->assertTrue($config->get('enabled'));
  }

}
