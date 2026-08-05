<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the exported People view config used at /admin/people.
 *
 * @group asu_user
 */
final class UserAdminPeopleViewConfigTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
    'filter',
    'user_bundle',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user', 'filter', 'views', 'user_bundle']);
  }

  /**
   * Sync config exposes name/email search and account type on the people view.
   *
   * - combine filter searches name and mail with identifier "user".
   * - type field and filter come from user_bundle integration.
   */
  public function testExportedPeopleViewHasSearchAndAccountType(): void {
    $path = DRUPAL_ROOT . '/../conf/cmi/views.view.user_admin_people.yml';
    $this->assertFileExists($path);

    $raw = Yaml::parse(file_get_contents($path));
    $this->assertIsArray($raw);
    $this->assertSame('user_admin_people', $raw['id'] ?? NULL);

    $filters = $raw['display']['default']['display_options']['filters'] ?? [];
    $this->assertArrayHasKey('combine', $filters);
    $combine = $filters['combine'];
    $this->assertTrue($combine['exposed'] ?? FALSE);
    $this->assertSame('user', $combine['expose']['identifier'] ?? NULL);
    $this->assertSame(['name' => 'name', 'mail' => 'mail'], $combine['fields'] ?? NULL);

    $this->assertArrayHasKey('type', $filters);
    $this->assertTrue($filters['type']['exposed'] ?? FALSE);

    $fields = $raw['display']['default']['display_options']['fields'] ?? [];
    $this->assertArrayHasKey('type', $fields);
    $this->assertSame('Account type', $fields['type']['label'] ?? NULL);

    $this->assertContains('user_bundle', $raw['dependencies']['module'] ?? []);
  }

  /**
   * Exported people view config can be installed and loaded as a view entity.
   *
   * - View entity is loadable after config import.
   * - Page display path remains admin/people/list.
   */
  public function testExportedPeopleViewInstallsAndLoads(): void {
    $path = DRUPAL_ROOT . '/../conf/cmi/views.view.user_admin_people.yml';
    $raw = Yaml::parse(file_get_contents($path));

    $this->container->get('config.storage')->write(
      'views.view.user_admin_people',
      $raw,
    );
    $this->container->get('config.factory')->reset('views.view.user_admin_people');

    $view = $this->container->get('entity_type.manager')
      ->getStorage('view')
      ->load('user_admin_people');
    $this->assertNotNull($view);

    $display = $view->getDisplay('page_1');
    $this->assertSame('admin/people/list', $display['display_options']['path'] ?? NULL);
  }

}
