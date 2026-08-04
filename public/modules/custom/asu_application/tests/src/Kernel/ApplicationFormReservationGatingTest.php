<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Entity\ApplicationType;
use Drupal\asu_content\Entity\Project;
use Drupal\config_terms\Entity\Term as ConfigTerm;
use Drupal\config_terms\Entity\Vocab;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;

/**
 * Tests ApplicationForm HITAS post-period reservation gating.
 *
 * Verifies that:
 * - After period + can_apply_afterwards enables reservation mode on the form
 * - In-period or can_apply_afterwards=false does not enable reservation mode
 * - Reservation mode limits selection to one apartment.
 *
 * @group asu_application
 *
 * @coversDefaultClass \Drupal\asu_application\Form\ApplicationForm
 */
final class ApplicationFormReservationGatingTest extends KernelTestBase {

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
    'options',
    'taxonomy',
    'datetime',
    'config_terms',
    'computed_field_plugin',
    'asu_api',
    'asu_content',
    'asu_application',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('application_type');
    $this->installConfig(['node', 'field', 'system', 'taxonomy']);
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'project', 'name' => 'Project'])->save();
    NodeType::create(['type' => 'apartment', 'name' => 'Apartment'])->save();

    foreach ([
      'field_archived' => 'boolean',
      'field_housing_company' => 'string',
      'field_street_address' => 'string',
      'field_apartment_count' => 'integer',
    ] as $field_name => $type) {
      $this->createNodeField($field_name, $type, 'project');
    }

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

    Vocabulary::create([
      'vid' => 'ownership_type',
      'name' => 'Ownership type',
    ])->save();

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
          'handler_settings' => [
            'target_bundles' => ['ownership_type' => 'ownership_type'],
          ],
        ],
      ])->save();
    }

    $this->createNodeField('field_can_apply_afterwards', 'boolean', 'project');
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

    $this->createNodeField('field_apartment_number', 'string', 'apartment');
    $this->createNodeField('field_apartment_structure', 'string', 'apartment');
    $this->createNodeField('field_living_area', 'decimal', 'apartment');
    $this->createNodeField('field_floor', 'integer', 'apartment');
    $this->createNodeField('field_floor_max', 'integer', 'apartment');
    $this->createNodeField('field_sales_price', 'decimal', 'apartment');
    $this->createNodeField('field_debt_free_sales_price', 'decimal', 'apartment');

    if (!Vocab::load('apartment_state_of_sale')) {
      Vocab::create([
        'id' => 'apartment_state_of_sale',
        'label' => 'Apartment state of sale',
      ])->save();
    }
    foreach (['free_for_reservations', 'reserved', 'open_for_applications'] as $term_id) {
      if (!ConfigTerm::load($term_id)) {
        ConfigTerm::create([
          'id' => $term_id,
          'vid' => 'apartment_state_of_sale',
          'label' => ucfirst(str_replace('_', ' ', $term_id)),
        ])->save();
      }
    }

    if (!FieldStorageConfig::loadByName('node', 'field_apartment_state_of_sale')) {
      FieldStorageConfig::create([
        'field_name' => 'field_apartment_state_of_sale',
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'settings' => ['target_type' => 'config_terms_term'],
      ])->save();
    }
    if (!FieldConfig::loadByName('node', 'apartment', 'field_apartment_state_of_sale')) {
      FieldConfig::create([
        'field_name' => 'field_apartment_state_of_sale',
        'entity_type' => 'node',
        'bundle' => 'apartment',
        'label' => 'State of sale',
      ])->save();
    }

    ApplicationType::create([
      'id' => 'hitas',
      'label' => 'Hitas',
    ])->save();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $this->installEntitySchema('asu_application');
  }

  /**
   * Creates a simple field on a node bundle.
   */
  private function createNodeField(string $field_name, string $type, string $bundle): void {
    if (!FieldStorageConfig::loadByName('node', $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => $type,
      ])->save();
    }
    if (!FieldConfig::loadByName('node', $bundle, $field_name)) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => $bundle,
        'label' => $field_name,
      ])->save();
    }
  }

  /**
   * Creates a HITAS project with one free apartment.
   */
  private function createHitasProject(
    string $startTime,
    string $endTime,
    bool $canApplyAfterwards,
  ): Project {
    $ownership = Term::create([
      'vid' => 'ownership_type',
      'name' => 'Hitas',
    ]);
    $ownership->save();

    $apartment = Node::create([
      'type' => 'apartment',
      'title' => 'A1',
      'status' => 1,
      'field_apartment_number' => 'A1',
      'field_apartment_structure' => '1h+k',
      'field_living_area' => 30,
      'field_floor' => 2,
      'field_floor_max' => 5,
      'field_sales_price' => 200000,
      'field_debt_free_sales_price' => 250000,
      'field_apartment_state_of_sale' => [
        ['target_id' => 'free_for_reservations'],
      ],
    ]);
    $apartment->save();

    $project = Node::create([
      'type' => 'project',
      'title' => 'Reservation Project',
      'status' => 1,
      'field_archived' => 0,
      'field_housing_company' => 'Reservation Co',
      'field_street_address' => 'Reserve St 1',
      'field_ownership_type' => [['target_id' => $ownership->id()]],
      'field_application_start_time' => $startTime,
      'field_application_end_time' => $endTime,
      'field_can_apply_afterwards' => [['value' => (int) $canApplyAfterwards]],
      'field_apartments' => [['target_id' => $apartment->id()]],
    ]);
    $project->save();
    $this->assertInstanceOf(Project::class, $project);

    return $project;
  }

  /**
   * Builds the application edit form for a saved application.
   */
  private function buildApplicationForm(Project $project): array {
    $user = User::create([
      'name' => 'customer-' . $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'status' => 1,
    ]);
    $user->save();
    $this->container->get('current_user')->setAccount($user);

    $application = Application::create([
      'bundle' => 'hitas',
      'uid' => $user->id(),
      'project_id' => (int) $project->id(),
      'project' => (int) $project->id(),
      'status' => 1,
    ]);
    $application->save();

    return $this->container->get('entity.form_builder')->getForm($application, 'default');
  }

  /**
   * After-period HITAS with can_apply_afterwards enables reservation mode.
   *
   * - Form flag #is_hitas_post_period_reservation is TRUE.
   * - Title uses reservation wording.
   * - Max apartments is limited to 1.
   */
  public function testAfterPeriodWithCanApplyAfterwardsEnablesReservationMode(): void {
    $project = $this->createHitasProject(
      '2020-01-01T00:00:00',
      '2020-06-01T00:00:00',
      canApplyAfterwards: TRUE,
    );

    $form = $this->buildApplicationForm($project);

    $this->assertTrue($form['#is_hitas_post_period_reservation']);
    $this->assertStringContainsString(
      'Make a reservation for',
      (string) $form['#title']
    );
    $this->assertSame(
      1,
      $form['#attached']['drupalSettings']['asuApplication']['maxApartments']
    );
  }

  /**
   * In-period HITAS does not enable reservation mode.
   */
  public function testInPeriodDoesNotEnableReservationMode(): void {
    $project = $this->createHitasProject(
      '2020-01-01T00:00:00',
      '2099-12-31T00:00:00',
      canApplyAfterwards: TRUE,
    );

    $form = $this->buildApplicationForm($project);

    $this->assertFalse(!empty($form['#is_hitas_post_period_reservation']));
    $this->assertStringContainsString('Application for', (string) $form['#title']);
  }

  /**
   * After-period HITAS without can_apply_afterwards is not reservation mode.
   */
  public function testAfterPeriodWithoutCanApplyAfterwardsIsNotReservationMode(): void {
    $project = $this->createHitasProject(
      '2020-01-01T00:00:00',
      '2020-06-01T00:00:00',
      canApplyAfterwards: FALSE,
    );

    $form = $this->buildApplicationForm($project);

    $this->assertFalse(!empty($form['#is_hitas_post_period_reservation']));
  }

}
