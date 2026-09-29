<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

/**
 * Tests that the project property number is exposed in the REST mapping.
 *
 * The Django service reads `project_property_number` from the apartment/project
 * REST payload and rejects SAP transmissions when it is missing. This field
 * lives on the project node as `field_property_number` and must be mapped.
 *
 * @group asu_rest
 */
final class SearchMapperProjectPropertyNumberTest extends SearchMapperKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createNodeField(
      'field_property_number',
      'project',
      'string',
      'Property number',
    );
  }

  /**
   * Maps the project property number when set.
   *
   * - Asserts the mapped payload contains the key.
   * - Asserts the value matches the field value on the node.
   */
  public function testProjectPropertyNumberIsMapped(): void {
    $mapped = $this->mapProject('Project One', [
      'field_property_number' => '053',
    ]);

    $this->assertArrayHasKey('project_property_number', $mapped);
    $this->assertSame('053', $mapped['project_property_number']);
  }

  /**
   * Maps an empty string when the property number is not set.
   *
   * - Asserts the key is always present so the consumer schema is stable.
   * - Asserts the value is an empty string rather than missing/NULL.
   */
  public function testProjectPropertyNumberDefaultsToEmptyString(): void {
    $mapped = $this->mapProject('Project Without Number');

    $this->assertArrayHasKey('project_property_number', $mapped);
    $this->assertSame('', $mapped['project_property_number']);
  }

}
