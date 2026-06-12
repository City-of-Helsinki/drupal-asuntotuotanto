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
 * Tests that project_use_complete_contract is exposed in the REST mapping.
 *
 * The Django service reads `project_use_complete_contract` from the
 * apartment/project REST payload to choose the correct HITAS contract PDF
 * template. The value lives on the project node as
 * `field_use_complete_contract` and must be mapped.
 *
 * @group asu_rest
 */
final class SearchMapperProjectUseCompleteContractTest extends KernelTestBase {

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
      'field_name' => 'field_use_complete_contract',
      'entity_type' => 'node',
      'type' => 'boolean',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_use_complete_contract',
      'entity_type' => 'node',
      'bundle' => 'project',
      'label' => 'Use complete apartment contract',
    ])->save();

    $this->mapper = $this->container->get('asu_rest.search_mapper');
  }

  /**
   * Maps project_use_complete_contract when enabled on the project.
   *
   * - Asserts the mapped payload contains the key.
   * - Asserts the value is TRUE when the field is set.
   */
  public function testProjectUseCompleteContractIsMappedWhenEnabled(): void {
    $project = Node::create([
      'type' => 'project',
      'title' => 'Complete contract project',
      'status' => 1,
      'field_use_complete_contract' => 1,
    ]);
    $project->save();

    $mapped = $this->mapper->mapProject($project);

    $this->assertArrayHasKey('project_use_complete_contract', $mapped);
    $this->assertTrue($mapped['project_use_complete_contract']);
  }

  /**
   * Maps project_use_complete_contract as FALSE when disabled.
   *
   * - Asserts the key is always present so the consumer schema is stable.
   * - Asserts the value is FALSE when the field is unset or off.
   */
  public function testProjectUseCompleteContractDefaultsToFalse(): void {
    $project = Node::create([
      'type' => 'project',
      'title' => 'Standard contract project',
      'status' => 1,
    ]);
    $project->save();

    $mapped = $this->mapper->mapProject($project);

    $this->assertArrayHasKey('project_use_complete_contract', $mapped);
    $this->assertFalse($mapped['project_use_complete_contract']);
  }

}
