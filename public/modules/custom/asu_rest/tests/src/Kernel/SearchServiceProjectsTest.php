<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

use Drupal\node\Entity\Node;

/**
 * Tests project search behavior in the search service.
 *
 * @group asu_rest
 */
final class SearchServiceProjectsTest extends SearchServiceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSearchTestContentModel();
    $this->createStateOfSaleVocabularyWithSoldTerm();
    $this->installNodeSchemaAndConfig();
    $this->createAndLoginUser();
    $this->initSearchService();
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
