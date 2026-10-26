<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_sanitized_dump\Kernel;

use Drupal\asu_sanitized_dump\Config\SanitizerConfig;
use Drupal\Component\Serialization\Yaml;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Every column that can hold personal data must have a sanitizer decision.
 *
 * @group asu_sanitized_dump
 */
final class SanitizerCoverageKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'datetime',
    'node',
    'options',
    'language',
    'asu_api',
    'asu_application',
    'asu_project_subscription',
    'asu_sanitized_dump',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (SanitizerConfig::PII_ENTITY_TYPES as $entity_type) {
      $this->installEntitySchema($entity_type);
    }
    $this->installSiteFieldStorages();
  }

  /**
   * Each PII entity field column is sanitized or its field marked safe.
   *
   * - Base fields and the site's exported config fields are installed
   * - Every field column in every table of the PII entity types is checked
   * - Drupal's structural columns (bundle, delta, ...) are skipped
   * - A column fails unless it has an expression or its field is in
   *   SAFE_FIELDS.
   */
  public function testEveryPiiEntityColumnIsClassified(): void {
    $expressions = SanitizerConfig::expressions();
    $entity_type_manager = $this->container->get('entity_type.manager');

    $unclassified = [];
    foreach (SanitizerConfig::PII_ENTITY_TYPES as $entity_type) {
      $safe_fields = SanitizerConfig::SAFE_FIELDS[$entity_type] ?? [];
      $mapping = $entity_type_manager->getStorage($entity_type)->getTableMapping();
      foreach ($mapping->getTableNames() as $table) {
        $table_columns = $mapping->getAllColumns($table);
        foreach ($mapping->getFieldNames($table) as $field) {
          if (in_array($field, $safe_fields, TRUE)) {
            continue;
          }
          $columns = array_intersect($mapping->getColumnNames($field), $table_columns);
          foreach ($columns as $column) {
            if (!isset($expressions[$table][$column])) {
              $unclassified[] = "$entity_type.$field ($table.$column)";
            }
          }
        }
      }
    }

    $this->assertSame(
      [],
      $unclassified,
      'Add these columns to SanitizerConfig::expressions() if they can hold personal data, otherwise add the field to SanitizerConfig::SAFE_FIELDS.'
    );
  }

  /**
   * Every expression targets a column that exists.
   *
   * - Expressions on PII entity tables name real columns, so a renamed or
   *   moved field cannot leave personal data unsanitized silently.
   */
  public function testExpressionsTargetExistingColumns(): void {
    $schema = $this->container->get('database')->schema();
    $entity_type_manager = $this->container->get('entity_type.manager');

    $entity_tables = [];
    foreach (SanitizerConfig::PII_ENTITY_TYPES as $entity_type) {
      $entity_tables = array_merge(
        $entity_tables,
        $entity_type_manager->getStorage($entity_type)->getTableMapping()->getTableNames(),
      );
    }

    $missing = [];
    foreach (SanitizerConfig::expressions() as $table => $columns) {
      if (!in_array($table, $entity_tables, TRUE)) {
        // Custom tables (e.g. the co-applicant map) are checked elsewhere.
        continue;
      }
      foreach (array_keys($columns) as $column) {
        if (!$schema->fieldExists($table, $column)) {
          $missing[] = "$table.$column";
        }
      }
    }

    $this->assertSame([], $missing, 'Sanitizer expressions target columns that do not exist.');
  }

  /**
   * Install the site's exported field storages for the PII entity types.
   */
  private function installSiteFieldStorages(): void {
    $config_dir = dirname($this->root) . '/conf/cmi';
    foreach (SanitizerConfig::PII_ENTITY_TYPES as $entity_type) {
      foreach (glob("$config_dir/field.storage.$entity_type.*.yml") ?: [] as $file) {
        $data = Yaml::decode(file_get_contents($file));
        if (!FieldStorageConfig::loadByName($entity_type, $data['field_name'])) {
          FieldStorageConfig::create($data)->save();
        }
      }
    }
  }

}
