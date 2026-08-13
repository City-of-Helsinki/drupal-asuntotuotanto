<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_content\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests reverse-reference collection used by computed apartment fields.
 *
 * Search API indexes apartments in batches. A growing static cache of
 * project referrers multiplies asu_computed_apartment_images and pollutes
 * Elasticsearch image_urls (and thus Etuovi/Oikotie exports).
 *
 * @group asu_content
 */
final class CollectReverseEntityTest extends KernelTestBase {

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
    'image',
    'computed_field_plugin',
    'asu_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installConfig(['node', 'field', 'image']);
    $this->installSchema('node', ['node_access']);

    NodeType::create([
      'type' => 'apartment',
      'name' => 'Apartment',
    ])->save();
    NodeType::create([
      'type' => 'project',
      'name' => 'Project',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_apartments',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_apartments',
      'entity_type' => 'node',
      'bundle' => 'project',
      'label' => 'Apartments',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => ['apartment' => 'apartment'],
        ],
      ],
    ])->save();

    // Required by asu_content_entity_presave() on project save.
    FieldStorageConfig::create([
      'field_name' => 'field_apartment_count',
      'entity_type' => 'node',
      'type' => 'integer',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_apartment_count',
      'entity_type' => 'node',
      'bundle' => 'project',
      'label' => 'Apartment count',
    ])->save();

    // Required by asu_content_entity_presave() on published apartment save.
    FieldStorageConfig::create([
      'field_name' => 'field_apartment_state_of_sale',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_apartment_state_of_sale',
      'entity_type' => 'node',
      'bundle' => 'apartment',
      'label' => 'State of sale',
    ])->save();
  }

  /**
   * Reverse refs stay unique when many apartments of one project are resolved.
   *
   * - Creates one project referencing several apartments.
   * - Resolves reverse references for each apartment in sequence (batch index).
   * - Asserts every apartment returns exactly one project referrer.
   * - Asserts referrer lists do not accumulate copies of the same project.
   */
  public function testReverseReferencesDoNotAccumulateAcrossApartments(): void {
    $apartments = [];
    for ($i = 1; $i <= 5; $i++) {
      $apartment = Node::create([
        'type' => 'apartment',
        'title' => "Apartment $i",
        'status' => 1,
      ]);
      $apartment->save();
      $apartments[] = $apartment;
    }

    $project = Node::create([
      'type' => 'project',
      'title' => 'Project One',
      'status' => 1,
      'field_apartments' => array_map(
        static fn(Node $apartment): array => ['target_id' => $apartment->id()],
        $apartments
      ),
    ]);
    $project->save();

    $collector = $this->container->get('asu_content.collect_reverse_entity');

    foreach ($apartments as $index => $apartment) {
      $references = $collector->getReverseReferences($apartment);
      $project_refs = array_values(array_filter(
        $references,
        static function (array $reference) use ($project): bool {
          $entity = $reference['referring_entity'] ?? NULL;
          return $entity instanceof Node
            && (int) $entity->id() === (int) $project->id();
        }
      ));

      $this->assertCount(
        1,
        $project_refs,
        sprintf(
          'Apartment %d (batch position %d) must reverse-reference the project once, got %d.',
          (int) $apartment->id(),
          $index + 1,
          count($project_refs)
        )
      );
    }
  }

}
