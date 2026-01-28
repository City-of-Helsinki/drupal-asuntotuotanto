<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

use Drupal\asu_rest\Service\SearchService;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

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
    'asu_rest',
  ];

  private SearchService $searchService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['node']);

    NodeType::create([
      'type' => 'project',
      'name' => 'Project',
    ])->save();

    $this->searchService = $this->container->get('asu_rest.search_service');
  }

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

  private function createProject(string $title): Node {
    $project = Node::create([
      'type' => 'project',
      'title' => $title,
      'status' => 1,
    ]);
    $project->save();
    return $project;
  }

}

