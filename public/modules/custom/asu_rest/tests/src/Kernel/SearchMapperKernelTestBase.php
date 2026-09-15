<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

use Drupal\asu_rest\Service\SearchMapper;
use Drupal\node\Entity\Node;

/**
 * Shared Kernel test setup for SearchMapper tests.
 */
abstract class SearchMapperKernelTestBase extends SearchServiceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
  ];

  /**
   * The mapper under test.
   *
   * @var \Drupal\asu_rest\Service\SearchMapper
   */
  protected SearchMapper $mapper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installNodeSchemaAndConfig();
    $this->ensureNodeType('project', 'Project');

    $this->mapper = $this->container->get('asu_rest.search_mapper');
  }

  /**
   * Create a project link field.
   *
   * @param string $fieldName
   *   Field machine name.
   * @param string $label
   *   Field label.
   * @param int $cardinality
   *   Field cardinality.
   */
  protected function createProjectLinkField(
    string $fieldName,
    string $label,
    int $cardinality = 1,
  ): void {
    $this->createNodeField(
      $fieldName,
      'project',
      'link',
      $label,
      $cardinality,
      [],
      [
        'link_type' => 17,
        'title' => 0,
      ],
    );
  }

  /**
   * Create and save a project node.
   *
   * @param string $title
   *   The project title.
   * @param array<string, mixed> $values
   *   Additional field values.
   *
   * @return \Drupal\node\Entity\Node
   *   The saved project node.
   */
  protected function createProjectNode(string $title, array $values = []): Node {
    return $this->createContentNode('project', $title, $values);
  }

  /**
   * Create and save an apartment node.
   *
   * @param string $title
   *   The apartment title.
   * @param array<string, mixed> $values
   *   Additional field values.
   *
   * @return \Drupal\node\Entity\Node
   *   The saved apartment node.
   */
  protected function createApartmentNode(string $title, array $values = []): Node {
    return $this->createContentNode('apartment', $title, $values);
  }

  /**
   * Map a newly created project node.
   *
   * @param string $title
   *   The project title.
   * @param array<string, mixed> $values
   *   Additional field values.
   *
   * @return array<string, mixed>
   *   Mapped project payload.
   */
  protected function mapProject(string $title, array $values = []): array {
    return $this->mapper->mapProject($this->createProjectNode($title, $values));
  }

}
