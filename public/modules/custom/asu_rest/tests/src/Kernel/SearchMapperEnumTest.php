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
   * Holding type terms use label fallback when machine-readable name is absent.
   */
  public function testProjectHoldingTypeUsesTaxonomyLabelFallback(): void {
    Vocabulary::create([
      'vid' => 'holding_type',
      'name' => 'Holding type',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_holding_type',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_holding_type',
      'entity_type' => 'node',
      'bundle' => 'project',
      'label' => 'Holding type',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'holding_type' => 'holding_type',
          ],
        ],
      ],
    ])->save();

    $term = Term::create([
      'vid' => 'holding_type',
      'name' => 'Right of residence apartment',
    ]);
    $term->save();

    $project = Node::create([
      'type' => 'project',
      'title' => 'Project Two',
      'status' => 1,
      'field_holding_type' => [
        ['target_id' => $term->id()],
      ],
    ]);
    $project->save();

    $mapped = $this->mapper->mapProject($project);
    $this->assertSame(
      'RIGHT_OF_RESIDENCE_APARTMENT',
      $mapped['project_holding_type'],
    );
  }

}
