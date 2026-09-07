<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

use Drupal\node\Entity\Node;

/**
 * Tests apartment search behavior in the search service.
 *
 * @group asu_rest
 */
final class SearchServiceApartmentsTest extends SearchServiceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSearchTestContentModel(TRUE);
    $this->createStateOfSaleVocabularyWithSoldTerm();
    $this->installNodeSchemaAndConfig();
    $this->createAndLoginUser();
    $this->initSearchService();
  }

  /**
   * Tests that project apartment search includes sold apartments.
   */
  public function testSearchApartmentsByProjectIncludesSoldApartments(): void {
    $availableApartment = $this->createApartment('Available apartment', 'available');
    $soldApartment = $this->createApartment('Sold apartment', 'sold');

    $project = Node::create([
      'type' => 'project',
      'title' => 'Project One',
      'status' => 1,
      'field_archived' => 0,
      'field_state_of_sale' => [
        ['target_id' => 'sold'],
      ],
      'field_apartments' => [
        ['target_id' => $availableApartment->id()],
        ['target_id' => $soldApartment->id()],
      ],
    ]);
    $project->save();
    $project = Node::load($project->id());

    $result = $this->searchService->searchApartments([], (int) $project->id(), 0, 1000);

    $this->assertSame(2, $result['total']);
    $uuids = array_map(static fn (Node $node): string => $node->uuid(), $result['items']);
    $this->assertContains($availableApartment->uuid(), $uuids);
    $this->assertContains($soldApartment->uuid(), $uuids);
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
   * Tests that apartments from archived projects are excluded by default.
   */
  public function testSearchApartmentsExcludesArchivedProjectsByDefault(): void {
    $activeApartment = $this->createApartment('Active project apartment');
    $archivedApartment = $this->createApartment('Archived project apartment');

    $this->createProjectWithApartment('Active Project', $activeApartment, FALSE);
    $this->createProjectWithApartment('Archived Project', $archivedApartment, TRUE);

    $result = $this->searchService->searchApartments([], NULL, 0, 1000);

    $this->assertSame(1, $result['total']);
    $this->assertCount(1, $result['items']);
    $this->assertSame($activeApartment->uuid(), $result['items'][0]->uuid());
  }

  /**
   * Tests include_archived=true for archived project apartments.
   */
  public function testSearchApartmentsIncludesArchivedProjectsWhenRequested(): void {
    $activeApartment = $this->createApartment('Active project apartment');
    $archivedApartment = $this->createApartment('Archived project apartment');

    $this->createProjectWithApartment('Active Project', $activeApartment, FALSE);
    $this->createProjectWithApartment('Archived Project', $archivedApartment, TRUE);

    $result = $this->searchService->searchApartments(
      ['include_archived' => 'true'],
      NULL,
      0,
      1000
    );

    $this->assertSame(2, $result['total']);
    $this->assertCount(2, $result['items']);

    $uuids = array_map(static fn (Node $node): string => $node->uuid(), $result['items']);
    $this->assertContains($activeApartment->uuid(), $uuids);
    $this->assertContains($archivedApartment->uuid(), $uuids);
  }

  /**
   * Creates an apartment node for testing.
   *
   * @param string $title
   *   The apartment title.
   * @param string $stateOfSale
   *   The apartment state of sale field value.
   *
   * @return \Drupal\node\Entity\Node
   *   The created apartment node.
   */
  private function createApartment(string $title, string $stateOfSale = 'available'): Node {
    $apartment = Node::create([
      'type' => 'apartment',
      'title' => $title,
      'status' => 1,
      'field_archived' => 0,
      'field_apartment_state_of_sale' => $stateOfSale,
    ]);
    $apartment->save();
    return $apartment;
  }

  /**
   * Creates a project node and links a single apartment node to it.
   *
   * @param string $title
   *   Project title.
   * @param \Drupal\node\Entity\Node $apartment
   *   The apartment node to attach.
   * @param bool $archived
   *   Whether the project is archived.
   */
  private function createProjectWithApartment(string $title, Node $apartment, bool $archived): void {
    $project = Node::create([
      'type' => 'project',
      'title' => $title,
      'status' => 1,
      'field_archived' => $archived ? 1 : 0,
      'field_state_of_sale' => [
        ['target_id' => 'sold'],
      ],
      'field_apartments' => [
        ['target_id' => $apartment->id()],
      ],
    ]);
    $project->save();
  }

}
