<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

use Drupal\asu_rest\Service\SearchMapper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests that the project property number is exposed in the REST mapping.
 *
 * The Django service reads `project_property_number` from the apartment/project
 * REST payload and rejects SAP transmissions when it is missing. This field
 * lives on the project node as `field_property_number` and must be mapped.
 *
 * @group asu_rest
 */
final class SearchMapperProjectPropertyNumberTest extends KernelTestBase {

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
   * The mapper under test.
   *
   * @var \Drupal\asu_rest\Service\SearchMapper
   */
  private SearchMapper $mapper;

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

    FieldStorageConfig::create([
      'field_name' => 'field_property_number',
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_property_number',
      'entity_type' => 'node',
      'bundle' => 'project',
      'label' => 'Property number',
    ])->save();

    $this->mapper = $this->container->get('asu_rest.search_mapper');
  }

  /**
   * Maps the project property number when set.
   *
   * - Asserts the mapped payload contains the key.
   * - Asserts the value matches the field value on the node.
   */
  public function testProjectPropertyNumberIsMapped(): void {
    $project = Node::create([
      'type' => 'project',
      'title' => 'Project One',
      'status' => 1,
      'field_property_number' => '053',
    ]);
    $project->save();

    $mapped = $this->mapper->mapProject($project);

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
    $project = Node::create([
      'type' => 'project',
      'title' => 'Project Without Number',
      'status' => 1,
    ]);
    $project->save();

    $mapped = $this->mapper->mapProject($project);

    $this->assertArrayHasKey('project_property_number', $mapped);
    $this->assertSame('', $mapped['project_property_number']);
  }

}
