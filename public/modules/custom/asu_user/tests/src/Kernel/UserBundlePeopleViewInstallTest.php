<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_user\Kernel;

use Drupal\Core\Entity\EntityMalformedException;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests user_bundle install does not corrupt the people view.
 *
 * @group asu_user
 */
final class UserBundlePeopleViewInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Production/CI installs do not enforce config schema on save. Disable it
   * here so the missing-view case matches site-install behavior.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user', 'filter', 'views']);
  }

  /**
   * Installing user_bundle without the people view must not create a stub.
   *
   * - During existing-config site install the optional people view may be
   *   absent when user_bundle installs.
   * - getEditable() always returns a config object, so user_bundle must not
   *   save display fragments onto a non-existent view.
   * - A stub without id breaks ViewsBlock discovery / theme install later.
   */
  public function testInstallWithoutPeopleViewDoesNotCreateMalformedConfig(): void {
    // Simulate existing-config install where the optional people view is not
    // present yet (it is not exported to conf/cmi in this project).
    $this->container->get('config.storage')->delete('views.view.user_admin_people');
    $this->container->get('config.factory')->reset('views.view.user_admin_people');
    $this->assertFalse(
      $this->container->get('config.storage')->exists('views.view.user_admin_people'),
      'Precondition: people view is not installed.',
    );

    $this->container->get('module_installer')->install(['user_bundle']);

    $raw = $this->container->get('config.storage')->read('views.view.user_admin_people');
    if ($raw !== FALSE) {
      $this->assertNotEmpty(
        $raw['id'] ?? NULL,
        'If people view config exists after install, it must have an id.',
      );
    }

    try {
      $this->container->get('entity_type.manager')->getStorage('view')->loadMultiple();
    }
    catch (EntityMalformedException $e) {
      $this->fail('View config must remain loadable after user_bundle install: ' . $e->getMessage());
    }
  }

  /**
   * Installing user_bundle with the people view adds the account type column.
   *
   * - Optional people view is installed first.
   * - user_bundle install adds the type field/filter used for account types.
   */
  public function testInstallWithPeopleViewAddsTypeField(): void {
    $this->container->get('config.installer')->installOptionalConfig(
      NULL,
      [
        'module' => 'user',
      ],
    );
    $this->assertTrue(
      $this->container->get('config.storage')->exists('views.view.user_admin_people'),
      'Precondition: people view optional config is installed.',
    );

    $this->container->get('module_installer')->install(['user_bundle']);

    $raw = $this->container->get('config.storage')->read('views.view.user_admin_people');
    $this->assertIsArray($raw);
    $this->assertSame('user_admin_people', $raw['id'] ?? NULL);
    $this->assertArrayHasKey(
      'type',
      $raw['display']['default']['display_options']['fields'] ?? [],
    );
    $this->assertArrayHasKey(
      'type',
      $raw['display']['default']['display_options']['filters'] ?? [],
    );
  }

}
