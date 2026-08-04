<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Entity\ApplicationType;
use Drupal\asu_content\Entity\Project;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\asu_content\Kernel\ProjectApartmentContentModelTrait;
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

  use ProjectApartmentContentModelTrait;

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

    $this->installEntitySchema('application_type');
    $this->installProjectApartmentContentModel([
      'free_for_reservations',
      'reserved',
      'open_for_applications',
    ]);

    foreach ([
      'field_apartment_structure' => 'string',
      'field_living_area' => 'decimal',
      'field_floor' => 'integer',
      'field_floor_max' => 'integer',
      'field_sales_price' => 'decimal',
      'field_debt_free_sales_price' => 'decimal',
    ] as $field_name => $type) {
      $this->createNodeField($field_name, $type, 'apartment');
    }

    ApplicationType::create([
      'id' => 'hitas',
      'label' => 'Hitas',
    ])->save();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $this->installEntitySchema('asu_application');
  }

  /**
   * Creates a HITAS project with one free apartment.
   */
  private function createHitasProject(
    string $startTime,
    string $endTime,
    bool $canApplyAfterwards,
  ): Project {
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

    return $this->createProject(
      $this->createOwnershipTerm('Hitas'),
      $startTime,
      $endTime,
      $canApplyAfterwards,
      [
        'title' => 'Reservation Project',
        'field_housing_company' => 'Reservation Co',
        'field_street_address' => 'Reserve St 1',
        'field_apartments' => [['target_id' => $apartment->id()]],
      ],
    );
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
