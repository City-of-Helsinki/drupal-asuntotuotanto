<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_hauki\Kernel;

use Drupal\helfi_hauki\Entity\Resource;
use Drupal\Tests\helfi_api_base\Kernel\MigrationTestBase;
use Drupal\Tests\helfi_hauki\Traits\MigrateTrait;

/**
 * Tests hauki migrations.
 *
 * @group helfi_hauki
 */
class MigrationTest extends MigrationTestBase {

  use MigrateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'address',
    'text',
    'key_value_field',
    'helfi_hauki',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() : void {
    parent::setUp();

    $this->installEntitySchema('hauki_resource');
    $this->installConfig(['helfi_hauki']);
  }

  /**
   * Tests service migration.
   */
  public function testServiceMigration() : void {
    $this->createResourceMigration();
    $entities = Resource::loadMultiple();
    $this->assertCount(20, $entities);

    foreach (['en', 'sv', 'fi'] as $langcode) {
      foreach ($entities as $entity) {
        $translation = $entity->getTranslation($langcode);
        $this->assertEquals($langcode, $translation->language()->getId());
        $this->assertEquals(sprintf('Name %s %s', $langcode, $translation->id()), $translation->label());
        $this->assertCount(1, $translation->getOrigins());
      }
    }
  }

}
