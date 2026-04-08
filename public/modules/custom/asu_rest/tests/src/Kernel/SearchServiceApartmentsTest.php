<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

use Drupal\asu_rest\Service\SearchService;
use Drupal\config_terms\Entity\Term;
use Drupal\config_terms\Entity\Vocab;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Tests apartment search behavior in the search service.
 *
 * @group asu_rest
 */
final class SearchServiceApartmentsTest extends KernelTestBase {

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
    'asu_rest',
  ];

  /**
   * The search service under test.
   *
   * @var \Drupal\asu_rest\Service\SearchService
   */
  private SearchService $searchService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');

    NodeType::create([
      'type' => 'apartment',
      'name' => 'Apartment',
    ])->save();

    NodeType::create([
      'type' => 'project',
      'name' => 'Project',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_archived',
      'entity_type' => 'node',
      'type' => 'boolean',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_archived',
      'entity_type' => 'node',
      'bundle' => 'apartment',
      'label' => 'Archived',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_apartment_state_of_sale',
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_apartment_state_of_sale',
      'entity_type' => 'node',
      'bundle' => 'apartment',
      'label' => 'Apartment state of sale',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_apartments',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'node',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_apartments',
      'entity_type' => 'node',
      'bundle' => 'project',
      'label' => 'Apartments',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => [
            'apartment' => 'apartment',
          ],
        ],
      ],
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
      ],
    ])->save();

    $vocab = Vocab::create([
      'id' => 'state_of_sale',
      'label' => 'State of sale',
    ]);
    $vocab->save();

    $term = Term::create([
      'id' => 'sold',
      'vid' => 'state_of_sale',
      'label' => 'Sold',
    ]);
    $term->save();

    $this->installEntitySchema('node');
    $this->installConfig(['node']);

    $user = User::create([
      'name' => 'test-admin',
      'status' => 1,
    ]);
    $user->save();
    $this->container->get('current_user')->setAccount($user);

    $this->searchService = $this->container->get('asu_rest.search_service');
  }

  /**
   * Tests that searchApartments filters results by UUID.
   */
  public function testSearchApartmentsFiltersByUuid(): void {
    $apartmentOne = $this->createApartment('Apartment One');
    $this->createApartment('Apartment Two');

    $project = Node::create([
      'type' => 'project',
      'title' => 'Project One',
      'status' => 1,
      'field_archived' => 0,
      'field_state_of_sale' => [
        ['target_id' => 'sold'],
      ],
      'field_apartments' => [
        ['target_id' => $apartmentOne->id()],
      ],
    ]);
    $project->save();

    $result = $this->searchService->searchApartments(
      ['uuid' => $apartmentOne->uuid()],
      NULL,
      0,
      1000
    );

    $this->assertSame(1, $result['total']);
    $this->assertCount(1, $result['items']);
    $this->assertSame($apartmentOne->uuid(), $result['items'][0]->uuid());
  }

  /**
   * Creates an apartment node for testing.
   *
   * @param string $title
   *   The apartment title.
   *
   * @return \Drupal\node\Entity\Node
   *   The created apartment node.
   */
  private function createApartment(string $title): Node {
    $apartment = Node::create([
      'type' => 'apartment',
      'title' => $title,
      'status' => 1,
      'field_archived' => 0,
      'field_apartment_state_of_sale' => 'available',
    ]);
    $apartment->save();
    return $apartment;
  }

}
