<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_content\Kernel;

use Drupal\asu_content\Entity\Apartment;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;

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

  use ProjectApartmentContentModelTrait;

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
    $this->installProjectApartmentContentModel(['free_for_reservations']);
  }

  /**
   * Late HITAS reservation URLs include the apartment node id query param.
   *
   * - Project is after application period with can_apply_afterwards.
   * - Apartment state is free_for_reservations.
   * - URL contains /application/add/hitas/ and apartment={nid}.
   */
  public function testHitasReservationUrlContainsApartmentNodeId(): void {
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

    $this->createProject(
      $this->createOwnershipTerm('Hitas'),
      '2020-01-01T00:00:00',
      '2020-06-01T00:00:00',
      canApplyAfterwards: TRUE,
      extraValues: [
        'field_apartments' => [['target_id' => $apartment->id()]],
      ],
    );

    $url = $apartment->getApplicationUrl();

    $this->assertStringContainsString('/application/add/hitas/', $url);
    $this->assertStringContainsString('apartment=' . $apartment->id(), $url);
  }

}
