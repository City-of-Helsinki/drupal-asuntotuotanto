<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_content\Kernel;

use Drupal\asu_content\Entity\Project;
use Drupal\config_terms\Entity\Term as ConfigTerm;
use Drupal\config_terms\Entity\Vocab;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Shared project/apartment content model for application URL kernel tests.
 */
trait ProjectApartmentContentModelTrait {

  /**
   * Install node schemas and project/apartment fields used by URL tests.
   *
   * @param string[] $apartmentStateTerms
   *   Optional apartment_state_of_sale term ids to ensure exist.
   * @param bool $withConfigTerms
   *   Whether to install apartment_state_of_sale config_terms field.
   */
  protected function installProjectApartmentContentModel(
    array $apartmentStateTerms = [],
    bool $withConfigTerms = TRUE,
  ): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['node', 'field', 'system', 'taxonomy']);
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'project', 'name' => 'Project'])->save();
    NodeType::create(['type' => 'apartment', 'name' => 'Apartment'])->save();

    foreach ([
      'field_archived' => 'boolean',
      'field_housing_company' => 'string',
      'field_street_address' => 'string',
      'field_apartment_count' => 'integer',
    ] as $field_name => $type) {
      $this->createNodeField($field_name, $type, 'project');
    }

    $this->ensureEntityReferenceField(
      'field_apartments',
      'project',
      'node',
      -1,
      'Apartments'
    );

    Vocabulary::create([
      'vid' => 'ownership_type',
      'name' => 'Ownership type',
    ])->save();

    $this->ensureEntityReferenceField(
      'field_ownership_type',
      'project',
      'taxonomy_term',
      1,
      'Ownership type',
      ['ownership_type' => 'ownership_type']
    );

    $this->createNodeField('field_can_apply_afterwards', 'boolean', 'project');
    foreach (['field_application_start_time', 'field_application_end_time'] as $field) {
      if (!FieldStorageConfig::loadByName('node', $field)) {
        FieldStorageConfig::create([
          'field_name' => $field,
          'entity_type' => 'node',
          'type' => 'datetime',
          'settings' => ['datetime_type' => 'datetime'],
        ])->save();
      }
      if (!FieldConfig::loadByName('node', 'project', $field)) {
        FieldConfig::create([
          'field_name' => $field,
          'entity_type' => 'node',
          'bundle' => 'project',
          'label' => $field,
        ])->save();
      }
    }

    $this->createNodeField('field_apartment_number', 'string', 'apartment');

    if ($withConfigTerms) {
      $this->ensureApartmentStateOfSale($apartmentStateTerms);
    }
  }

  /**
   * Ensure apartment_state_of_sale vocabulary, terms and field exist.
   *
   * @param string[] $termIds
   *   Term machine names to create.
   */
  protected function ensureApartmentStateOfSale(array $termIds = ['free_for_reservations']): void {
    if (!Vocab::load('apartment_state_of_sale')) {
      Vocab::create([
        'id' => 'apartment_state_of_sale',
        'label' => 'Apartment state of sale',
      ])->save();
    }
    foreach ($termIds as $term_id) {
      if (!ConfigTerm::load($term_id)) {
        ConfigTerm::create([
          'id' => $term_id,
          'vid' => 'apartment_state_of_sale',
          'label' => ucfirst(str_replace('_', ' ', $term_id)),
        ])->save();
      }
    }

    if (!FieldStorageConfig::loadByName('node', 'field_apartment_state_of_sale')) {
      FieldStorageConfig::create([
        'field_name' => 'field_apartment_state_of_sale',
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'settings' => ['target_type' => 'config_terms_term'],
      ])->save();
    }
    if (!FieldConfig::loadByName('node', 'apartment', 'field_apartment_state_of_sale')) {
      FieldConfig::create([
        'field_name' => 'field_apartment_state_of_sale',
        'entity_type' => 'node',
        'bundle' => 'apartment',
        'label' => 'State of sale',
      ])->save();
    }
  }

  /**
   * Creates a simple field on a node bundle.
   *
   * @param string $field_name
   *   Field machine name.
   * @param string $type
   *   Field type.
   * @param string $bundle
   *   Node bundle.
   */
  protected function createNodeField(string $field_name, string $type, string $bundle): void {
    if (!FieldStorageConfig::loadByName('node', $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => $type,
      ])->save();
    }
    if (!FieldConfig::loadByName('node', $bundle, $field_name)) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => $bundle,
        'label' => $field_name,
      ])->save();
    }
  }

  /**
   * Ensure an entity_reference field exists on a project/apartment bundle.
   *
   * @param string $fieldName
   *   Field machine name.
   * @param string $bundle
   *   Node bundle.
   * @param string $targetType
   *   Target entity type.
   * @param int $cardinality
   *   Field cardinality.
   * @param string $label
   *   Field label.
   * @param array $targetBundles
   *   Optional target bundles for taxonomy references.
   */
  protected function ensureEntityReferenceField(
    string $fieldName,
    string $bundle,
    string $targetType,
    int $cardinality,
    string $label,
    array $targetBundles = [],
  ): void {
    if (!FieldStorageConfig::loadByName('node', $fieldName)) {
      FieldStorageConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'cardinality' => $cardinality,
        'settings' => ['target_type' => $targetType],
      ])->save();
    }
    if (!FieldConfig::loadByName('node', $bundle, $fieldName)) {
      $settings = [];
      if ($targetBundles) {
        $settings = [
          'handler' => 'default:' . $targetType,
          'handler_settings' => [
            'target_bundles' => $targetBundles,
          ],
        ];
      }
      FieldConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'node',
        'bundle' => $bundle,
        'label' => $label,
        'settings' => $settings,
      ])->save();
    }
  }

  /**
   * Creates an ownership type taxonomy term.
   *
   * @param string $name
   *   The term name (e.g. "Hitas" or "Haso").
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   The saved term.
   */
  protected function createOwnershipTerm(string $name): Term {
    $term = Term::create([
      'vid' => 'ownership_type',
      'name' => $name,
    ]);
    $term->save();
    return $term;
  }

  /**
   * Creates a project node with given ownership type and time settings.
   *
   * @param \Drupal\taxonomy\Entity\Term $ownershipTerm
   *   The ownership type term.
   * @param string $startTime
   *   ISO 8601 application start time.
   * @param string $endTime
   *   ISO 8601 application end time.
   * @param bool $canApplyAfterwards
   *   Whether the project allows late applications.
   * @param array $extraValues
   *   Extra node field values (e.g. field_apartments).
   *
   * @return \Drupal\asu_content\Entity\Project
   *   The saved project entity.
   */
  protected function createProject(
    Term $ownershipTerm,
    string $startTime,
    string $endTime,
    bool $canApplyAfterwards,
    array $extraValues = [],
  ): Project {
    $project = Node::create($extraValues + [
      'type' => 'project',
      'title' => 'Test Project',
      'status' => 1,
      'field_archived' => 0,
      'field_housing_company' => 'Test Co',
      'field_street_address' => 'Test St 1',
      'field_ownership_type' => [['target_id' => $ownershipTerm->id()]],
      'field_application_start_time' => $startTime,
      'field_application_end_time' => $endTime,
      'field_can_apply_afterwards' => [['value' => (int) $canApplyAfterwards]],
    ]);
    $project->save();
    $this->assertInstanceOf(Project::class, $project);
    return $project;
  }

}
