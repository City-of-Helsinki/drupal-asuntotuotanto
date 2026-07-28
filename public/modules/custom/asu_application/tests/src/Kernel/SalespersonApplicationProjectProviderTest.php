<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\asu_application\Service\SalespersonApplicationProjectProvider;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests salesperson project loading for the create-application form.
 *
 * @group asu_application
 *
 * @coversDefaultClass \Drupal\asu_application\Service\SalespersonApplicationProjectProvider
 */
final class SalespersonApplicationProjectProviderTest extends KernelTestBase {

  use SalespersonApplicationFormTestTrait;

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
    'config_terms',
  ];

  /**
   * The provider under test.
   *
   * @var \Drupal\asu_application\Service\SalespersonApplicationProjectProvider
   */
  private SalespersonApplicationProjectProvider $provider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['node']);
    $this->installSalespersonProjectContentModel();

    $this->provider = new SalespersonApplicationProjectProvider(
      $this->container->get('entity_type.manager'),
    );
  }

  /**
   * Tests that selectable projects exclude upcoming but include ready.
   */
  public function testGetSelectableProjectsExcludesUpcomingIncludesReady(): void {
    $upcoming = $this->createTestProject('Upcoming Co', 'Upcoming St 1', 'upcoming');
    $ready = $this->createTestProject('Ready Co', 'Ready St 2', 'ready');
    $this->createTestProject('For Sale Co', 'Sale St 3', 'for_sale');

    $projects = $this->provider->getSelectableProjects();

    $this->assertArrayNotHasKey((int) $upcoming->id(), $projects);
    $this->assertArrayHasKey((int) $ready->id(), $projects);
    $this->assertSame('Ready Co', $projects[(int) $ready->id()]['title']);
    $this->assertSame('Ready St 2', $projects[(int) $ready->id()]['address']);
    $this->assertCount(2, $projects);
  }

  /**
   * Tests that loadProjectLabels returns title and address for any project id.
   */
  public function testLoadProjectLabelsReturnsTitleAndAddress(): void {
    $upcoming = $this->createTestProject('Past Co', 'Past St 9', 'upcoming');

    $projects = $this->provider->loadProjectLabels([(int) $upcoming->id()]);

    $this->assertSame([
      (int) $upcoming->id() => [
        'title' => 'Past Co',
        'address' => 'Past St 9',
        'ownership_type' => NULL,
      ],
    ], $projects);
  }

}
