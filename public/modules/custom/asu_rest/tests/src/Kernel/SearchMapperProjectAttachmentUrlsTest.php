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
 * Tests that project attachment URLs are exposed in the REST mapping.
 *
 * Django reads `project_attachment_urls` from the apartment REST payload to
 * build offer materialbank links. The values come from the project link field
 * `field_attachments_url`.
 *
 * @group asu_rest
 */
final class SearchMapperProjectAttachmentUrlsTest extends KernelTestBase {

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
      'field_name' => 'field_attachments_url',
      'entity_type' => 'node',
      'type' => 'link',
      'cardinality' => -1,
      'settings' => [],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_attachments_url',
      'entity_type' => 'node',
      'bundle' => 'project',
      'label' => 'Attachments / URL',
      'settings' => [
        'link_type' => 17,
        'title' => 0,
      ],
    ])->save();

    $this->mapper = $this->container->get('asu_rest.search_mapper');
  }

  /**
   * Maps project attachment URLs when the link field is populated.
   *
   * - Asserts the mapped payload contains `project_attachment_urls`.
   * - Asserts external URLs are returned as absolute strings.
   */
  public function testProjectAttachmentUrlsAreMapped(): void {
    $project = Node::create([
      'type' => 'project',
      'title' => 'Project One',
      'status' => 1,
      'field_attachments_url' => [
        ['uri' => 'https://example.com/mediabank/project'],
        ['uri' => 'https://example.com/mediabank/project-2'],
      ],
    ]);
    $project->save();

    $mapped = $this->mapper->mapProject($project);

    $this->assertArrayHasKey('project_attachment_urls', $mapped);
    $this->assertSame(
      [
        'https://example.com/mediabank/project',
        'https://example.com/mediabank/project-2',
      ],
      $mapped['project_attachment_urls']
    );
  }

  /**
   * Maps an empty list when attachment URLs are not set.
   *
   * - Asserts the key is always present so the consumer schema is stable.
   */
  public function testProjectAttachmentUrlsDefaultToEmptyList(): void {
    $project = Node::create([
      'type' => 'project',
      'title' => 'Project Without Attachments',
      'status' => 1,
    ]);
    $project->save();

    $mapped = $this->mapper->mapProject($project);

    $this->assertArrayHasKey('project_attachment_urls', $mapped);
    $this->assertSame([], $mapped['project_attachment_urls']);
  }

}
