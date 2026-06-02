<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

use Drupal\asu_rest\Service\SearchMapper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests enum serialization in SearchMapper.
 *
 * @group asu_rest
 */
final class SearchMapperEnumTest extends KernelTestBase {

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
    'file',
    'config_terms',
    'taxonomy',
    'asu_rest',
  ];

  /**
   * The mapper under test.
   *
   * @var \Drupal\asu_rest\Service\SearchMapper
   */
  private SearchMapper $mapper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['node', 'taxonomy']);

    NodeType::create([
      'type' => 'project',
      'name' => 'Project',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_state_of_sale',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'config_terms_term',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_state_of_sale',
      'entity_type' => 'node',
      'bundle' => 'project',
      'label' => 'State of sale',
      'settings' => [
        'handler' => 'default:config_terms_term',
        'handler_settings' => [
          'target_vocab' => 'state_of_sale',
        ],
      ],
    ])->save();

    $this->mapper = $this->container->get('asu_rest.search_mapper');
  }

  /**
   * Ensures enum values are taken from machine-readable field.
   */
  public function testProjectStateOfSaleUsesConfigEntityId(): void {
    $vocab = $this->container
      ->get('entity_type.manager')
      ->getStorage('config_terms_vocab')
      ->create([
        'id' => 'state_of_sale',
        'label' => 'State of sale',
      ]);
    $vocab->save();

    $term = $this->container
      ->get('entity_type.manager')
      ->getStorage('config_terms_term')
      ->create([
        'id' => 'sold',
        'vid' => 'state_of_sale',
        'label' => 'Myyty',
      ]);
    $term->save();

    $project = Node::create([
      'type' => 'project',
      'title' => 'Project One',
      'status' => 1,
      'field_state_of_sale' => [
        ['target_id' => $term->id()],
      ],
    ]);
    $project->save();

    $mapped = $this->mapper->mapProject($project);
    $this->assertSame('SOLD', $mapped['project_state_of_sale']);
  }

  /**
   * Provides taxonomy enum fields that lack term-level machine-readable names.
   *
   * @return array<string, array{string, string, string, string}>
   *   Vocabulary, field name, term label, and expected enum per case.
   */
  public static function taxonomyEnumFieldsWithoutMachineNameProvider(): array {
    return [
      'holding_type' => [
        'holding_type',
        'field_holding_type',
        'Right of residence apartment',
        'RIGHT_OF_RESIDENCE_APARTMENT',
      ],
      'building_type' => [
        'building_types',
        'field_building_type',
        'Block of flats',
        'BLOCK_OF_FLATS',
      ],
      'new_development_status' => [
        'new_development_status',
        'field_new_development_status',
        'Under construction',
        'UNDER_CONSTRUCTION',
      ],
    ];
  }

  /**
   * Taxonomy enum fields use English label fallback when name is absent.
   *
   * @dataProvider taxonomyEnumFieldsWithoutMachineNameProvider
   */
  public function testProjectTaxonomyEnumUsesLabelFallback(
    string $vocabularyId,
    string $fieldName,
    string $termLabel,
    string $expectedEnum,
  ): void {
    $this->createProjectTaxonomyEnumField($vocabularyId, $fieldName);

    $term = Term::create([
      'vid' => $vocabularyId,
      'name' => $termLabel,
    ]);
    $term->save();

    $project = Node::create([
      'type' => 'project',
      'title' => 'Project enum test',
      'status' => 1,
      $fieldName => [
        ['target_id' => $term->id()],
      ],
    ]);
    $project->save();

    $mapped = $this->mapper->mapProject($project);
    $mappedKey = str_replace('field_', 'project_', $fieldName);
    $this->assertSame($expectedEnum, $mapped[$mappedKey]);
  }

  /**
   * Prefers field_machine_readable_name over the term label when set.
   */
  public function testProjectBuildingTypePrefersMachineReadableName(): void {
    $this->createProjectTaxonomyEnumField('building_types', 'field_building_type');

    FieldStorageConfig::create([
      'field_name' => 'field_machine_readable_name',
      'entity_type' => 'taxonomy_term',
      'type' => 'string',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_machine_readable_name',
      'entity_type' => 'taxonomy_term',
      'bundle' => 'building_types',
      'label' => 'Machine readable name',
    ])->save();

    $term = Term::create([
      'vid' => 'building_types',
      'name' => 'Block of flats',
      'field_machine_readable_name' => 'detached_house',
    ]);
    $term->save();

    $project = Node::create([
      'type' => 'project',
      'title' => 'Project machine name test',
      'status' => 1,
      'field_building_type' => [
        ['target_id' => $term->id()],
      ],
    ]);
    $project->save();

    $mapped = $this->mapper->mapProject($project);
    $this->assertSame('DETACHED_HOUSE', $mapped['project_building_type']);
  }

  /**
   * Creates a project taxonomy enum reference field.
   */
  private function createProjectTaxonomyEnumField(
    string $vocabularyId,
    string $fieldName,
  ): void {
    if (!Vocabulary::load($vocabularyId)) {
      Vocabulary::create([
        'vid' => $vocabularyId,
        'name' => $vocabularyId,
      ])->save();
    }

    if (!FieldStorageConfig::loadByName('node', $fieldName)) {
      FieldStorageConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'settings' => [
          'target_type' => 'taxonomy_term',
        ],
      ])->save();
    }

    if (!FieldConfig::loadByName('node', 'project', $fieldName)) {
      FieldConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'node',
        'bundle' => 'project',
        'label' => $fieldName,
        'settings' => [
          'handler' => 'default:taxonomy_term',
          'handler_settings' => [
            'target_bundles' => [
              $vocabularyId => $vocabularyId,
            ],
          ],
        ],
      ])->save();
    }
  }

}
