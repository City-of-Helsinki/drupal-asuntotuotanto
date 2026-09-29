<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_content\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;

/**
 * Tests asu_computed_apartment_images does not emit duplicate URLs.
 *
 * @group asu_content
 */
final class ApartmentImagesTest extends KernelTestBase {

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
    $this->installConfig(['node', 'field', 'image', 'system']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);

    NodeType::create([
      'type' => 'apartment',
      'name' => 'Apartment',
    ])->save();
    NodeType::create([
      'type' => 'project',
      'name' => 'Project',
    ])->save();

    $this->createImageField('field_images', 'apartment', -1);
    $this->createImageField('field_floorplan', 'apartment', 1);
    $this->createImageField('field_shared_apartment_images', 'project', -1);

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

    ImageStyle::create([
      'name' => '3_2_m',
      'label' => '3:2 M',
    ])->save();
  }

  /**
   * Computed images stay unique when reverse refs repeat the same project.
   *
   * - Stubs CollectReverseEntity to return the project N times (bug amplifier).
   * - Apartment has one floorplan + one image; project has one shared image.
   * - Asserts the computed field returns three unique URLs (not 3 * N).
   */
  public function testComputedImagesAreNotDuplicatedWhenReverseRefsRepeat(): void {
    $floorplan = $this->createImageFile('floorplan.jpg');
    $apartment_image = $this->createImageFile('apartment.jpg');
    $shared_image = $this->createImageFile('shared.jpg');

    $apartment = Node::create([
      'type' => 'apartment',
      'title' => 'Apartment 1',
      'status' => 1,
      'field_floorplan' => [['target_id' => $floorplan->id()]],
      'field_images' => [['target_id' => $apartment_image->id()]],
    ]);
    $apartment->save();

    $project = Node::create([
      'type' => 'project',
      'title' => 'Project One',
      'status' => 1,
      'field_shared_apartment_images' => [['target_id' => $shared_image->id()]],
      'field_apartments' => [['target_id' => $apartment->id()]],
    ]);
    $project->save();

    $repeat = 5;
    $stub = $this->createRepeatingReverseEntityStub($project, $repeat);

    $apartment = Node::load($apartment->id());
    $field = $apartment->get('asu_computed_apartment_images');

    $reverse_property = new \ReflectionProperty($field, 'reverseEntities');
    $reverse_property->setAccessible(TRUE);
    $reverse_property->setValue($field, $stub);

    // Force recomputation after injecting the stub.
    $computed_property = new \ReflectionProperty($field, 'valueComputed');
    $computed_property->setAccessible(TRUE);
    $computed_property->setValue($field, FALSE);
    $list_property = new \ReflectionProperty($field, 'list');
    $list_property->setAccessible(TRUE);
    $list_property->setValue($field, []);

    $urls = [];
    foreach ($field as $item) {
      $raw = $item->getValue();
      if (is_string($raw) && $raw !== '') {
        $urls[] = $raw;
        continue;
      }
      if (!is_array($raw)) {
        continue;
      }
      $url = $raw['#markup'] ?? $raw['value'] ?? NULL;
      if (is_string($url) && $url !== '') {
        $urls[] = $url;
      }
    }

    $this->assertCount(
      3,
      $urls,
      sprintf(
        'Expected 3 image URLs (floorplan + shared + apartment), got %d: %s',
        count($urls),
        implode(', ', $urls)
      )
    );
    $this->assertSame($urls, array_values(array_unique($urls)), 'Computed image URLs must be unique.');
  }

  /**
   * Creates an image field on a node bundle.
   */
  private function createImageField(string $field_name, string $bundle, int $cardinality): void {
    if (!FieldStorageConfig::loadByName('node', $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'image',
        'cardinality' => $cardinality,
        'settings' => [
          'target_type' => 'file',
          'uri_scheme' => 'public',
        ],
      ])->save();
    }

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => $field_name,
    ])->save();
  }

  /**
   * Creates a permanent image file entity with a real URI.
   */
  private function createImageFile(string $filename): File {
    $directory = 'public://apartment_images_test';
    \Drupal::service('file_system')->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY
    );
    $uri = $directory . '/' . $filename;
    file_put_contents(
      \Drupal::service('file_system')->realpath($directory) . '/' . $filename,
      base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
    );

    $file = File::create([
      'uri' => $uri,
      'filename' => $filename,
      'status' => 1,
    ]);
    $file->save();
    return $file;
  }

  /**
   * Builds a stub that repeats one project referrer N times.
   */
  private function createRepeatingReverseEntityStub(NodeInterface $project, int $times): object {
    return new class($project, $times) {

      /**
       * Project node returned as referrer.
       */
      private NodeInterface $projectNode;

      /**
       * How many times to repeat the project referrer.
       */
      private int $times;

      public function __construct(NodeInterface $project, int $times) {
        $this->projectNode = $project;
        $this->times = $times;
      }

      /**
       * Returns the project as a reverse reference, repeated $times times.
       */
      public function getReverseReferences(Node $entity): array {
        $references = [];
        for ($i = 0; $i < $this->times; $i++) {
          $references[] = [
            'referring_entity_type' => 'node',
            'field_name' => 'field_apartments',
            'referring_entity_id' => $this->projectNode->id(),
            'referring_entity' => $this->projectNode,
          ];
        }
        return $references;
      }

    };
  }

}
