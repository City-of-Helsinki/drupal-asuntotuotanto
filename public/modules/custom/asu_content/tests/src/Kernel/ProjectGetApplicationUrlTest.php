<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_content\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests Project::getApplicationUrl() HITAS post-period reservation routing.
 *
 * Verifies that:
 * - HITAS + after period + can_apply_afterwards + FREE_FOR_RESERVATIONS
 *   returns the /application/add/hitas/{id} URL (not the contact URL).
 * - HITAS + after period + can_apply_afterwards=false + FREE_FOR_RESERVATIONS
 *   returns the contact URL.
 * - HASO + after period + can_apply_afterwards still returns application URL.
 * - In-period HITAS always returns application URL.
 *
 * @group asu_content
 *
 * @coversDefaultClass \Drupal\asu_content\Entity\Project
 */
final class ProjectGetApplicationUrlTest extends KernelTestBase {

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
    'taxonomy',
    'datetime',
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
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['node', 'field', 'system', 'taxonomy']);
    $this->installSchema('node', ['node_access']);

    // Create project node type.
    NodeType::create([
      'type' => 'project',
      'name' => 'Project',
    ])->save();

    // Required by asu_content_entity_presave hook.
    foreach ([
      'field_archived' => 'boolean',
      'field_housing_company' => 'string',
      'field_street_address' => 'string',
      'field_apartment_count' => 'integer',
    ] as $field_name => $type) {
      if (!FieldStorageConfig::loadByName('node', $field_name)) {
        FieldStorageConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'node',
          'type' => $type,
        ])->save();
      }
      if (!FieldConfig::loadByName('node', 'project', $field_name)) {
        FieldConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'node',
          'bundle' => 'project',
          'label' => $field_name,
        ])->save();
      }
    }

    // Required by asu_content_entity_presave: field_apartments reference.
    if (!FieldStorageConfig::loadByName('node', 'field_apartments')) {
      FieldStorageConfig::create([
        'field_name' => 'field_apartments',
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'cardinality' => -1,
        'settings' => ['target_type' => 'node'],
      ])->save();
    }
    if (!FieldConfig::loadByName('node', 'project', 'field_apartments')) {
      FieldConfig::create([
        'field_name' => 'field_apartments',
        'entity_type' => 'node',
        'bundle' => 'project',
        'label' => 'Apartments',
      ])->save();
    }

    // Create ownership_type vocabulary.
    Vocabulary::create([
      'vid' => 'ownership_type',
      'name' => 'Ownership type',
    ])->save();

    // Create ownership type entity_reference field on project.
    if (!FieldStorageConfig::loadByName('node', 'field_ownership_type')) {
      FieldStorageConfig::create([
        'field_name' => 'field_ownership_type',
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'cardinality' => 1,
        'settings' => ['target_type' => 'taxonomy_term'],
      ])->save();
    }
    if (!FieldConfig::loadByName('node', 'project', 'field_ownership_type')) {
      FieldConfig::create([
        'field_name' => 'field_ownership_type',
        'entity_type' => 'node',
        'bundle' => 'project',
        'label' => 'Ownership type',
        'settings' => [
          'handler' => 'default:taxonomy_term',
          'handler_settings' => ['target_bundles' => ['ownership_type' => 'ownership_type']],
        ],
      ])->save();
    }

    // Create can_apply_afterwards boolean field on project.
    if (!FieldStorageConfig::loadByName('node', 'field_can_apply_afterwards')) {
      FieldStorageConfig::create([
        'field_name' => 'field_can_apply_afterwards',
        'entity_type' => 'node',
        'type' => 'boolean',
      ])->save();
    }
    if (!FieldConfig::loadByName('node', 'project', 'field_can_apply_afterwards')) {
      FieldConfig::create([
        'field_name' => 'field_can_apply_afterwards',
        'entity_type' => 'node',
        'bundle' => 'project',
        'label' => 'Can apply afterwards',
      ])->save();
    }

    // Create application time fields on project.
    foreach (['field_application_start_time', 'field_application_end_time'] as $field) {
      if (!FieldStorageConfig::loadByName('node', $field)) {
        FieldStorageConfig::create([
          'field_name' => $field,
          'entity_type' => 'node',
          'type' => 'datetime',
          'settings' => ['datetime_type' => 'datetime'],
        ])->save();
      }
      if (!FieldConfig::loadByName('node', 'project', $field)) {
        FieldConfig::create([
          'field_name' => $field,
          'entity_type' => 'node',
          'bundle' => 'project',
          'label' => $field,
        ])->save();
      }
    }
  }

  /**
   * Creates an ownership type taxonomy term.
   *
   * @param string $name
   *   The term name (e.g. "Hitas" or "Haso").
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   The saved term.
   */
  private function createOwnershipTerm(string $name): Term {
    $term = Term::create([
      'vid' => 'ownership_type',
      'name' => $name,
    ]);
    $term->save();
    return $term;
  }

  /**
   * Creates a project node with given ownership type and time settings.
   *
   * @param \Drupal\taxonomy\Entity\Term $ownershipTerm
   *   The ownership type term.
   * @param string $startTime
   *   ISO 8601 application start time.
   * @param string $endTime
   *   ISO 8601 application end time.
   * @param bool $canApplyAfterwards
   *   Whether the project allows late applications.
   *
   * @return \Drupal\asu_content\Entity\Project
   *   The saved project entity.
   */
  private function createProject(
    Term $ownershipTerm,
    string $startTime,
    string $endTime,
    bool $canApplyAfterwards,
  ): object {
    $project = Node::create([
      'type' => 'project',
      'title' => 'Test Project',
      'status' => 1,
      'field_archived' => 0,
      'field_housing_company' => 'Test Co',
      'field_street_address' => 'Test St 1',
      'field_ownership_type' => [['target_id' => $ownershipTerm->id()]],
      'field_application_start_time' => $startTime,
      'field_application_end_time' => $endTime,
      'field_can_apply_afterwards' => [['value' => (int) $canApplyAfterwards]],
    ]);
    $project->save();
    return $project;
  }

  /**
   * Tests late HITAS reservations link to the application form.
   *
   * A free apartment after the period with can_apply_afterwards returns the
   * application form URL, not the contact URL.
   */
  public function testHitasAfterPeriodFreeApartmentReturnsApplicationUrl(): void {
    $term = $this->createOwnershipTerm('Hitas');
    $project = $this->createProject(
      $term,
      '2020-01-01T00:00:00',
      '2020-06-01T00:00:00',
      canApplyAfterwards: TRUE,
    );

    $url = $project->getApplicationUrl(NULL, 'FREE_FOR_RESERVATIONS');

    $this->assertStringContainsString('/application/add/hitas/', $url);
    $this->assertStringNotContainsString('/contact/', $url);
  }

  /**
   * Tests that HITAS without late applications links to the contact form.
   *
   * A free apartment after the period without can_apply_afterwards returns
   * the contact URL.
   */
  public function testHitasAfterPeriodFreeApartmentWithoutCanApplyReturnsContactUrl(): void {
    $term = $this->createOwnershipTerm('Hitas');
    $project = $this->createProject(
      $term,
      '2020-01-01T00:00:00',
      '2020-06-01T00:00:00',
      canApplyAfterwards: FALSE,
    );

    $url = $project->getApplicationUrl(NULL, 'FREE_FOR_RESERVATIONS');

    $this->assertStringContainsString('/contact/', $url);
    $this->assertStringNotContainsString('/application/add/', $url);
  }

  /**
   * Tests that HASO late applications are unaffected.
   *
   * After the period with can_apply_afterwards the application form URL is
   * still returned (existing behaviour unchanged).
   */
  public function testHasoAfterPeriodReturnsApplicationUrl(): void {
    $term = $this->createOwnershipTerm('Haso');
    $project = $this->createProject(
      $term,
      '2020-01-01T00:00:00',
      '2020-06-01T00:00:00',
      canApplyAfterwards: TRUE,
    );

    $url = $project->getApplicationUrl();

    $this->assertStringContainsString('/application/add/haso/', $url);
    $this->assertStringNotContainsString('/contact/', $url);
  }

  /**
   * Tests that an active HITAS project links to the application form.
   *
   * Projects inside the application period always return the application
   * form URL.
   */
  public function testHitasInPeriodReturnsApplicationUrl(): void {
    $term = $this->createOwnershipTerm('Hitas');
    $project = $this->createProject(
      $term,
      '2020-01-01T00:00:00',
      '2099-12-31T00:00:00',
      canApplyAfterwards: FALSE,
    );

    $url = $project->getApplicationUrl();

    $this->assertStringContainsString('/application/add/hitas/', $url);
    $this->assertStringNotContainsString('/contact/', $url);
  }

}
