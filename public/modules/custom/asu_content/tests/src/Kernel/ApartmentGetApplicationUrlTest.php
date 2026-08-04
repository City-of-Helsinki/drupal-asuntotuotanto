<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_content\Kernel;

use Drupal\asu_content\Entity\Apartment;
use Drupal\asu_content\Entity\Project;
use Drupal\config_terms\Entity\Term as ConfigTerm;
use Drupal\config_terms\Entity\Vocab;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests Apartment::getApplicationUrl() includes apartment node id.
 *
 * Verifies that:
 * - HITAS post-period free apartments link to /application/add/hitas/
 * - The URL contains apartment={nid} for Drupal-rendered reservation links.
 *
 * @group asu_content
 *
 * @coversDefaultClass \Drupal\asu_content\Entity\Apartment
 */
final class ApartmentGetApplicationUrlTest extends KernelTestBase {

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
    'taxonomy',
    'datetime',
    'config_terms',
    'computed_field_plugin',
    'asu_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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

    if (!FieldStorageConfig::loadByName('node', 'field_apartments')) {
      FieldStorageConfig::create([
        'field_name' => 'field_apartments',
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'cardinality' => -1,
        'settings' => ['target_type' => 'node'],
      ])->save();
    }
    if (!FieldConfig::loadByName('node', 'project', 'field_apartments')) {
      FieldConfig::create([
        'field_name' => 'field_apartments',
        'entity_type' => 'node',
        'bundle' => 'project',
        'label' => 'Apartments',
      ])->save();
    }

    Vocabulary::create([
      'vid' => 'ownership_type',
      'name' => 'Ownership type',
    ])->save();

    if (!FieldStorageConfig::loadByName('node', 'field_ownership_type')) {
      FieldStorageConfig::create([
        'field_name' => 'field_ownership_type',
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'cardinality' => 1,
        'settings' => ['target_type' => 'taxonomy_term'],
      ])->save();
    }
    if (!FieldConfig::loadByName('node', 'project', 'field_ownership_type')) {
      FieldConfig::create([
        'field_name' => 'field_ownership_type',
        'entity_type' => 'node',
        'bundle' => 'project',
        'label' => 'Ownership type',
        'settings' => [
          'handler' => 'default:taxonomy_term',
          'handler_settings' => [
            'target_bundles' => ['ownership_type' => 'ownership_type'],
          ],
        ],
      ])->save();
    }

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

    if (!Vocab::load('apartment_state_of_sale')) {
      Vocab::create([
        'id' => 'apartment_state_of_sale',
        'label' => 'Apartment state of sale',
      ])->save();
    }
    if (!ConfigTerm::load('free_for_reservations')) {
      ConfigTerm::create([
        'id' => 'free_for_reservations',
        'vid' => 'apartment_state_of_sale',
        'label' => 'Free for reservations',
      ])->save();
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
  private function createNodeField(string $field_name, string $type, string $bundle): void {
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
   * Late HITAS reservation URLs include the apartment node id query param.
   *
   * - Project is after application period with can_apply_afterwards.
   * - Apartment state is free_for_reservations.
   * - URL contains /application/add/hitas/ and apartment={nid}.
   */
  public function testHitasReservationUrlContainsApartmentNodeId(): void {
    $ownership = Term::create([
      'vid' => 'ownership_type',
      'name' => 'Hitas',
    ]);
    $ownership->save();

    $apartment = Node::create([
      'type' => 'apartment',
      'title' => 'A1',
      'status' => 1,
      'field_apartment_number' => 'A1',
      'field_apartment_state_of_sale' => [
        ['target_id' => 'free_for_reservations'],
      ],
    ]);
    $apartment->save();
    $this->assertInstanceOf(Apartment::class, $apartment);

    $project = Node::create([
      'type' => 'project',
      'title' => 'Test Project',
      'status' => 1,
      'field_archived' => 0,
      'field_housing_company' => 'Test Co',
      'field_street_address' => 'Test St 1',
      'field_ownership_type' => [['target_id' => $ownership->id()]],
      'field_application_start_time' => '2020-01-01T00:00:00',
      'field_application_end_time' => '2020-06-01T00:00:00',
      'field_can_apply_afterwards' => [['value' => 1]],
      'field_apartments' => [['target_id' => $apartment->id()]],
    ]);
    $project->save();
    $this->assertInstanceOf(Project::class, $project);

    $url = $apartment->getApplicationUrl();

    $this->assertStringContainsString('/application/add/hitas/', $url);
    $this->assertStringContainsString('apartment=' . $apartment->id(), $url);
  }

}
