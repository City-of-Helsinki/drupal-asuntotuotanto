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
 * Tests project search behavior in the search service.
 *
 * @group asu_rest
 */
final class SearchServiceProjectsTest extends KernelTestBase {

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
      'bundle' => 'project',
      'label' => 'Archived',
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
   * Tests that searchProjects returns all projects when no filters are applied.
   */
  public function testSearchProjectsReturnsAllProjectsWhenNoFilters(): void {
    $projectOne = $this->createProject('Project One');
    $projectTwo = $this->createProject('Project Two');

    $result = $this->searchService->searchProjects([], 0, 1000);

    $this->assertSame(2, $result['total']);
    $this->assertCount(2, $result['items']);
    $expectedUuids = [$projectOne->uuid(), $projectTwo->uuid()];
    $actualUuids = array_map(
      static fn (Node $project) => $project->uuid(),
      $result['items']
    );
    sort($expectedUuids);
    sort($actualUuids);
    $this->assertSame($expectedUuids, $actualUuids);
  }

  /**
   * Tests that searchProjects filters results by project UUID.
   */
  public function testSearchProjectsFiltersByProjectUuid(): void {
    $projectOne = $this->createProject('Project One');
    $this->createProject('Project Two');

    $result = $this->searchService->searchProjects(
      ['project_uuid' => $projectOne->uuid()],
      0,
      1000
    );

    $this->assertSame(1, $result['total']);
    $this->assertCount(1, $result['items']);
    $this->assertSame($projectOne->uuid(), $result['items'][0]->uuid());
  }

  /**
   * Tests that searchProjects accepts hyphenated project-uuid parameter.
   */
  public function testSearchProjectsFiltersByProjectUuidHyphenated(): void {
    $projectOne = $this->createProject('Project One');
    $this->createProject('Project Two');

    $result = $this->searchService->searchProjects(
      ['project-uuid' => $projectOne->uuid()],
      0,
      1000
    );

    $this->assertSame(1, $result['total']);
    $this->assertCount(1, $result['items']);
    $this->assertSame($projectOne->uuid(), $result['items'][0]->uuid());
  }

  /**
   * Creates a project node for testing.
   *
   * @param string $title
   *   The project title.
   *
   * @return \Drupal\node\Entity\Node
   *   The created project node.
   */
  private function createProject(string $title): Node {
    $project = Node::create([
      'type' => 'project',
      'title' => $title,
      'status' => 1,
      'field_archived' => 0,
      'field_state_of_sale' => [
        ['target_id' => 'sold'],
      ],
    ]);
    $project->save();
    return $project;
  }

}
