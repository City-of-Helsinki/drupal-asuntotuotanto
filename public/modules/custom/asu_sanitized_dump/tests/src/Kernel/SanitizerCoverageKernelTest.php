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
   *   data/safe_fields.yml.
   */
  public function testEveryPiiEntityColumnIsClassified(): void {
    $expressions = SanitizerConfig::expressions();

    $unclassified = [];
    foreach (SanitizerConfig::PII_ENTITY_TYPES as $entity_type) {
      $safe_fields = SanitizerConfig::safeFields($entity_type);
      foreach ($this->fieldColumns($entity_type) as [$table, $field, $column]) {
        if (!in_array($field, $safe_fields, TRUE) && !isset($expressions[$table][$column])) {
          $unclassified[] = "$entity_type.$field ($table.$column)";
        }
      }
    }

    $this->assertSame(
      [],
      $unclassified,
      'Add these columns to SanitizerConfig::expressions() if they can hold '
      . 'personal data, otherwise add the field to data/safe_fields.yml.'
    );
  }

  /**
   * Yield every field column stored in the entity type's tables.
   *
   * Drupal's structural columns (bundle, delta, ...) are not field columns
   * and are skipped.
   *
   * @param string $entity_type
   *   Entity type id.
   *
   * @return \Generator<array{string, string, string}>
   *   [table, field name, column] triples.
   */
  private function fieldColumns(string $entity_type): \Generator {
    $mapping = $this->container->get('entity_type.manager')->getStorage($entity_type)->getTableMapping();
    foreach ($mapping->getTableNames() as $table) {
      $table_columns = $mapping->getAllColumns($table);
      foreach ($mapping->getFieldNames($table) as $field) {
        foreach (array_intersect($mapping->getColumnNames($field), $table_columns) as $column) {
          yield [$table, $field, $column];
        }
      }
    }
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
   * Custom tables built by their real update hooks are fully classified.
   *
   * - asu_application_co_applicant_map is created by asu_application
   *   update hooks 9013 and 9016, as on production
   * - Every column has an expression or is listed in CUSTOM_TABLES.
   */
  public function testCustomTablesAreClassified(): void {
    $this->container->get('module_handler')->loadInclude('asu_application', 'install');
    asu_application_update_9013();
    asu_application_update_9016();

    $connection = $this->container->get('database');
    $table_columns = [];
    foreach (array_keys(SanitizerConfig::CUSTOM_TABLES) as $table) {
      $this->assertTrue($connection->schema()->tableExists($table), "$table was not created.");
      $table_columns[$table] = $connection->query('SHOW COLUMNS FROM {' . $table . '}')->fetchCol();
    }

    $this->assertSame([], SanitizerConfig::unclassifiedCustomColumns($table_columns));
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
