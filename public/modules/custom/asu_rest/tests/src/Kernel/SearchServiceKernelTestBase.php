<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

use Drupal\asu_rest\Service\SearchService;
use Drupal\config_terms\Entity\Term;
use Drupal\config_terms\Entity\Vocab;
use Drupal\KernelTests\KernelTestBase;
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

