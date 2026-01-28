<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

use Drupal\asu_rest\Service\SearchService;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

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
      'type' => 'apartment',
      'name' => 'Apartment',
    ])->save();

    $this->searchService = $this->container->get('asu_rest.search_service');
  }

  public function testSearchApartmentsFiltersByUuid(): void {
    $apartmentOne = $this->createApartment('Apartment One');
    $this->createApartment('Apartment Two');

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

  private function createApartment(string $title): Node {
    $apartment = Node::create([
      'type' => 'apartment',
      'title' => $title,
      'status' => 1,
    ]);
    $apartment->save();
    return $apartment;
  }

}

