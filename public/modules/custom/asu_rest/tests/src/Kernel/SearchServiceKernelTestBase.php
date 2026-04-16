<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

use Drupal\asu_rest\Service\SearchService;
use Drupal\config_terms\Entity\Term;
use Drupal\config_terms\Entity\Vocab;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
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
   * Create node types and fields commonly used by search service tests.
   *
   * @param bool $withApartments
   *   Whether to also create the apartment content model and a project-to-
   *   apartments reference field.
   */
  protected function installSearchTestContentModel(bool $withApartments = FALSE): void {
    NodeType::create([
      'type' => 'project',
      'name' => 'Project',
    ])->save();

    if ($withApartments) {
      NodeType::create([
        'type' => 'apartment',
        'name' => 'Apartment',
      ])->save();
    }

    FieldStorageConfig::create([
      'field_name' => 'field_archived',
      'entity_type' => 'node',
      'type' => 'boolean',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_archived',
      'entity_type' => 'node',
      'bundle' => 'project',
      'label' => 'Archived',
    ])->save();

    if ($withApartments) {
      FieldConfig::create([
        'field_name' => 'field_archived',
        'entity_type' => 'node',
        'bundle' => 'apartment',
        'label' => 'Archived',
      ])->save();
    }

    FieldStorageConfig::create([
      'field_name' => 'field_state_of_sale',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'config_terms_term',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_state_of_sale',
      'entity_type' => 'node',
      'bundle' => 'project',
      'label' => 'State of sale',
      'settings' => [
        'handler' => 'default:config_terms_term',
      ],
    ])->save();

    if ($withApartments) {
      FieldStorageConfig::create([
        'field_name' => 'field_apartment_state_of_sale',
        'entity_type' => 'node',
        'type' => 'string',
      ])->save();

      FieldConfig::create([
        'field_name' => 'field_apartment_state_of_sale',
        'entity_type' => 'node',
        'bundle' => 'apartment',
        'label' => 'Apartment state of sale',
      ])->save();

      FieldStorageConfig::create([
        'field_name' => 'field_apartments',
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'settings' => [
          'target_type' => 'node',
        ],
      ])->save();

      FieldConfig::create([
        'field_name' => 'field_apartments',
        'entity_type' => 'node',
        'bundle' => 'project',
        'label' => 'Apartments',
        'settings' => [
          'handler' => 'default:node',
          'handler_settings' => [
            'target_bundles' => [
              'apartment' => 'apartment',
            ],
          ],
        ],
      ])->save();
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
