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
