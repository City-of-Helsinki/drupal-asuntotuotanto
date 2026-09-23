<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests enum serialization in SearchMapper.
 *
 * @group asu_rest
 */
final class SearchMapperEnumTest extends SearchMapperKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['taxonomy']);

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
        'handler_settings' => [
          'target_vocab' => 'state_of_sale',
        ],
      ],
    );
  }

  /**
   * Ensures enum values are taken from machine-readable field.
   */
  public function testProjectStateOfSaleUsesConfigEntityId(): void {
    $vocab = $this->container
      ->get('entity_type.manager')
      ->getStorage('config_terms_vocab')
      ->create([
        'id' => 'state_of_sale',
        'label' => 'State of sale',
      ]);
    $vocab->save();

    $term = $this->container
      ->get('entity_type.manager')
      ->getStorage('config_terms_term')
      ->create([
        'id' => 'sold',
        'vid' => 'state_of_sale',
        'label' => 'Myyty',
      ]);
    $term->save();

    $mapped = $this->mapProject('Project One', [
      'field_state_of_sale' => [
        ['target_id' => $term->id()],
      ],
    ]);
    $this->assertSame('SOLD', $mapped['project_state_of_sale']);
  }

  /**
   * Provides taxonomy enum fields that lack term-level machine-readable names.
   *
   * @return array<string, array{string, string, string, string}>
   *   Vocabulary, field name, term label, and expected enum per case.
   */
  public static function taxonomyEnumFieldsWithoutMachineNameProvider(): array {
    return [
      'holding_type' => [
        'holding_type',
        'field_holding_type',
        'Right of residence apartment',
        'RIGHT_OF_RESIDENCE_APARTMENT',
      ],
      'building_type' => [
        'building_types',
        'field_building_type',
        'Block of flats',
        'BLOCK_OF_FLATS',
      ],
      'new_development_status' => [
        'new_development_status',
        'field_new_development_status',
        'Under construction',
        'UNDER_CONSTRUCTION',
      ],
    ];
  }

  /**
   * Taxonomy enum fields use English label fallback when name is absent.
   *
   * @dataProvider taxonomyEnumFieldsWithoutMachineNameProvider
   */
  public function testProjectTaxonomyEnumUsesLabelFallback(
    string $vocabularyId,
    string $fieldName,
    string $termLabel,
    string $expectedEnum,
  ): void {
    $this->createProjectTaxonomyEnumField($vocabularyId, $fieldName);

    $term = Term::create([
      'vid' => $vocabularyId,
      'name' => $termLabel,
    ]);
    $term->save();

    $mapped = $this->mapProject('Project enum test', [
      $fieldName => [
        ['target_id' => $term->id()],
      ],
    ]);
    $mappedKey = str_replace('field_', 'project_', $fieldName);
    $this->assertSame($expectedEnum, $mapped[$mappedKey]);
  }

  /**
   * Prefers field_machine_readable_name over the term label when set.
   */
  public function testProjectBuildingTypePrefersMachineReadableName(): void {
    $this->createProjectTaxonomyEnumField('building_types', 'field_building_type');

    $this->createEntityField(
      'taxonomy_term',
      'field_machine_readable_name',
      'building_types',
      'string',
      'Machine readable name',
    );

    $term = Term::create([
      'vid' => 'building_types',
      'name' => 'Block of flats',
      'field_machine_readable_name' => 'detached_house',
    ]);
    $term->save();

    $mapped = $this->mapProject('Project machine name test', [
      'field_building_type' => [
        ['target_id' => $term->id()],
      ],
    ]);
    $this->assertSame('DETACHED_HOUSE', $mapped['project_building_type']);
  }

  /**
   * Creates a project taxonomy enum reference field.
   */
  private function createProjectTaxonomyEnumField(
    string $vocabularyId,
    string $fieldName,
  ): void {
    if (!Vocabulary::load($vocabularyId)) {
      Vocabulary::create([
        'vid' => $vocabularyId,
        'name' => $vocabularyId,
      ])->save();
    }

    $this->createNodeField(
      $fieldName,
      'project',
      'entity_reference',
      $fieldName,
      1,
      [
        'target_type' => 'taxonomy_term',
      ],
      [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            $vocabularyId => $vocabularyId,
          ],
        ],
      ],
    );
  }

}
