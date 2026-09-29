<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

use Drupal\asu_rest\Service\SearchService;
use Drupal\config_terms\Entity\Term;
use Drupal\config_terms\Entity\Vocab;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Shared Kernel test setup for asu_rest search service tests.
 */
abstract class SearchServiceKernelTestBase extends KernelTestBase {

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
   * The search service under test.
   *
   * @var \Drupal\asu_rest\Service\SearchService
   */
  protected SearchService $searchService;

  /**
   * Create a node type if it does not already exist.
   *
   * @param string $type
   *   Node type machine name.
   * @param string $name
   *   Human-readable type name.
   */
  protected function ensureNodeType(string $type, string $name): void {
    if (!NodeType::load($type)) {
      NodeType::create([
        'type' => $type,
        'name' => $name,
      ])->save();
    }
  }

  /**
   * Create a field on an entity bundle.
   *
   * @param string $entityType
   *   Entity type ID.
   * @param string $fieldName
   *   Field machine name.
   * @param string $bundle
   *   Entity bundle.
   * @param string $type
   *   Field type plugin ID.
   * @param string $label
   *   Field label.
   * @param int $cardinality
   *   Field cardinality.
   * @param array<string, mixed> $storageSettings
   *   Field storage settings.
   * @param array<string, mixed> $instanceSettings
   *   Field instance settings.
   */
  protected function createEntityField(
    string $entityType,
    string $fieldName,
    string $bundle,
    string $type,
    string $label,
    int $cardinality = 1,
    array $storageSettings = [],
    array $instanceSettings = [],
  ): void {
    if (!FieldStorageConfig::loadByName($entityType, $fieldName)) {
      FieldStorageConfig::create([
        'field_name' => $fieldName,
        'entity_type' => $entityType,
        'type' => $type,
        'cardinality' => $cardinality,
        'settings' => $storageSettings,
      ])->save();
    }

    if (!FieldConfig::loadByName($entityType, $bundle, $fieldName)) {
      FieldConfig::create([
        'field_name' => $fieldName,
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'label' => $label,
        'settings' => $instanceSettings,
      ])->save();
    }
  }

  /**
   * Create a node field on a bundle.
   *
   * @param string $fieldName
   *   Field machine name.
   * @param string $bundle
   *   Node bundle.
   * @param string $type
   *   Field type plugin ID.
   * @param string $label
   *   Field label.
   * @param int $cardinality
   *   Field cardinality.
   * @param array<string, mixed> $storageSettings
   *   Field storage settings.
   * @param array<string, mixed> $instanceSettings
   *   Field instance settings.
   */
  protected function createNodeField(
    string $fieldName,
    string $bundle,
    string $type,
    string $label,
    int $cardinality = 1,
    array $storageSettings = [],
    array $instanceSettings = [],
  ): void {
    $this->createEntityField(
      'node',
      $fieldName,
      $bundle,
      $type,
      $label,
      $cardinality,
      $storageSettings,
      $instanceSettings,
    );
  }

  /**
   * Create node types and fields commonly used by search service tests.
   *
   * @param bool $withApartments
   *   Whether to also create the apartment content model and a project-to-
   *   apartments reference field.
   */
  protected function installSearchTestContentModel(bool $withApartments = FALSE): void {
    $this->ensureNodeType('project', 'Project');
    if ($withApartments) {
      $this->ensureNodeType('apartment', 'Apartment');
    }

    $this->createNodeField('field_archived', 'project', 'boolean', 'Archived');
    if ($withApartments) {
      $this->createNodeField('field_archived', 'apartment', 'boolean', 'Archived');
    }

    $this->createNodeField(
      'field_state_of_sale',
      'project',
      'entity_reference',
      'State of sale',
      1,
      [
        'target_type' => 'config_terms_term',
      ],
      [
        'handler' => 'default:config_terms_term',
      ],
    );

    if ($withApartments) {
      $this->createNodeField(
        'field_apartment_state_of_sale',
        'apartment',
        'string',
        'Apartment state of sale',
      );
      $this->createNodeField(
        'field_apartments',
        'project',
        'entity_reference',
        'Apartments',
        -1,
        [
          'target_type' => 'node',
        ],
        [
          'handler' => 'default:node',
          'handler_settings' => [
            'target_bundles' => [
              'apartment' => 'apartment',
            ],
          ],
        ],
      );
    }
  }

  /**
   * Create the "state_of_sale" vocab and the "sold" term.
   */
  protected function createStateOfSaleVocabularyWithSoldTerm(): void {
    $vocab = Vocab::create([
      'id' => 'state_of_sale',
      'label' => 'State of sale',
    ]);
    $vocab->save();

    $term = Term::create([
      'id' => 'sold',
      'vid' => 'state_of_sale',
      'label' => 'Sold',
    ]);
    $term->save();
  }

  /**
   * Create and save a content node.
   *
   * @param string $type
   *   Node type machine name.
   * @param string $title
   *   Node title.
   * @param array<string, mixed> $values
   *   Additional field values.
   *
   * @return \Drupal\node\Entity\Node
   *   The saved node.
   */
  protected function createContentNode(string $type, string $title, array $values = []): Node {
    $node = Node::create([
      'type' => $type,
      'title' => $title,
      'status' => 1,
    ] + $values);
    $node->save();
    return $node;
  }

  /**
   * Install node schema/config used by search service queries.
   */
  protected function installNodeSchemaAndConfig(): void {
    $this->installEntitySchema('node');
    $this->installConfig(['node']);
  }

  /**
   * Create a logged-in user for tests that rely on current_user.
   */
  protected function createAndLoginUser(string $name = 'test-admin'): void {
    $this->installEntitySchema('user');

    $user = User::create([
      'name' => $name,
      'status' => 1,
    ]);
    $user->save();
    $this->container->get('current_user')->setAccount($user);
  }

  /**
   * Initialize the search service from the container.
   */
  protected function initSearchService(): void {
    $this->searchService = $this->container->get('asu_rest.search_service');
  }

}
