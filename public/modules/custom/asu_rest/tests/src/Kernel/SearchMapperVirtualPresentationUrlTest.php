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
 * Tests that project virtual presentation URLs are exposed in the REST mapping.
 *
 * Django reads `project_virtual_presentation_url` for the Oikotie
 * VirtualPresentation element, which must be an http(s) URL or omitted.
 *
 * @group asu_rest
 */
final class SearchMapperVirtualPresentationUrlTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'link',
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
      'field_name' => 'field_virtual_presentation_url',
      'entity_type' => 'node',
      'type' => 'link',
      'cardinality' => 1,
      'settings' => [],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_virtual_presentation_url',
      'entity_type' => 'node',
      'bundle' => 'project',
      'label' => 'Virtual presentation URL',
      'settings' => [
        'link_type' => 17,
        'title' => 0,
      ],
    ])->save();

    $this->mapper = $this->container->get('asu_rest.search_mapper');
  }

  /**
   * Maps the project virtual presentation URL when the link field is set.
   *
   * - Asserts the mapped payload contains `project_virtual_presentation_url`.
   * - Asserts an external URI is returned as an absolute string.
   */
  public function testProjectVirtualPresentationUrlIsMapped(): void {
    $project = Node::create([
      'type' => 'project',
      'title' => 'Project With Tour',
      'status' => 1,
      'field_virtual_presentation_url' => [
        ['uri' => 'https://example.com/virtual-tour'],
      ],
    ]);
    $project->save();

    $mapped = $this->mapper->mapProject($project);

    $this->assertArrayHasKey('project_virtual_presentation_url', $mapped);
    $this->assertSame(
      'https://example.com/virtual-tour',
      $mapped['project_virtual_presentation_url'],
    );
  }

  /**
   * Maps an empty string when the virtual presentation URL is not set.
   *
   * - Asserts the key is always present so the consumer schema is stable.
   */
  public function testProjectVirtualPresentationUrlDefaultsToEmptyString(): void {
    $project = Node::create([
      'type' => 'project',
      'title' => 'Project Without Tour',
      'status' => 1,
    ]);
    $project->save();

    $mapped = $this->mapper->mapProject($project);

    $this->assertArrayHasKey('project_virtual_presentation_url', $mapped);
    $this->assertSame('', $mapped['project_virtual_presentation_url']);
  }

}
