<?php

declare(strict_types = 1);

namespace Drupal\Tests\asu_content\Functional;

use Drupal\node\Entity\Node;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test elasticsearch indexing.
 *
 * @group asu_elastic
 */
final class FieldTest extends ExistingSiteBase {

  /**
   * Test project fields.
   */
  public function testProjectFields() {
    $requiredFields = [
      'field_application_start_time',
      'field_application_end_time',
      'field_ownership_type',
      'field_apartments',
      'field_state_of_sale',
      'field_holding_type',
      'field_district',
      'field_street_address',
      'field_services',
      'field_postal_code',
      'field_city',
      'field_main_image',
      'field_building_type',
      'field_salesperson',
      'field_archived',
    ];

    $project = Node::create([
      'type' => 'project',
      'status' => FALSE,
    ]);

    foreach ($requiredFields as $fieldName) {
      $this->assertTrue(
        $project->hasField($fieldName),
        "field $fieldName exists on project",
      );
    }
  }

  /**
   * Test apartment fields.
   */
  public function testApartmentFields() {
    $requiredFields = [
      'field_apartment_state_of_sale',
      'field_right_of_occupancy_payment',
      'field_apartment_structure',
      'field_apartment_number',
      'field_images',
      'field_sales_price',
      'field_debt_free_sales_price',
      'field_right_of_occupancy_fee',
      'field_right_of_occupancy_deposit',
      'field_floorplan',
    ];

    $apartment = Node::create([
      'type' => 'apartment',
      'status' => FALSE,
    ]);

    foreach ($requiredFields as $fieldName) {
      $this->assertTrue(
        $apartment->hasField($fieldName),
        "field $fieldName exists on apartment",
      );
    }
  }

}
