<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_content\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests countFieldData tolerates missing dedicated field tables.
 *
 * @group asu_content
 */
final class CountFieldDataMissingTableTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['node', 'field', 'system']);
    $this->installSchema('node', ['node_access']);

    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_missing_table_test',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'user',
      ],
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_missing_table_test',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Missing table test',
    ])->save();
  }

  /**
   * countFieldData returns empty when the revision field table is absent.
   *
   * - Mirrors existing-config site install where field config is imported
   *   before dedicated tables exist (or after tables were dropped).
   * - Without the core guard this throws SQLSTATE[42S02].
   */
  public function testCountFieldDataWithMissingRevisionTable(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $definitions = $this->container->get('entity_field.manager')
      ->getFieldStorageDefinitions('node');
    $definition = $definitions['field_missing_table_test'];

    $table_mapping = $storage->getTableMapping();
    $revision_table = $table_mapping->getDedicatedRevisionTableName($definition);
    $this->assertTrue(
      $this->container->get('database')->schema()->tableExists($revision_table),
      'Precondition: revision field table exists after field install.',
    );

    $this->container->get('database')->schema()->dropTable($revision_table);
    $this->assertFalse(
      $this->container->get('database')->schema()->tableExists($revision_table),
    );

    $count = $storage->countFieldData($definition, TRUE);
    $this->assertFalse($count);
    $this->assertSame(0, $storage->countFieldData($definition, FALSE));
  }

}
