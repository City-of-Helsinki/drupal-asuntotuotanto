<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_content\Kernel;

use Drupal\KernelTests\KernelTestBase;

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
    // Project URL tests only need the project content model.
    $this->installProjectApartmentContentModel(withConfigTerms: FALSE);
  }

  /**
   * Tests late HITAS reservations link to the application form.
   *
   * A free apartment after the period with can_apply_afterwards returns the
   * application form URL, not the contact URL.
   */
  public function testHitasAfterPeriodFreeApartmentReturnsApplicationUrl(): void {
    $project = $this->createProject(
      $this->createOwnershipTerm('Hitas'),
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
    $project = $this->createProject(
      $this->createOwnershipTerm('Hitas'),
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
    $project = $this->createProject(
      $this->createOwnershipTerm('Haso'),
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
    $project = $this->createProject(
      $this->createOwnershipTerm('Hitas'),
      '2020-01-01T00:00:00',
      '2099-12-31T00:00:00',
      canApplyAfterwards: FALSE,
    );

    $url = $project->getApplicationUrl();

    $this->assertStringContainsString('/application/add/hitas/', $url);
    $this->assertStringNotContainsString('/contact/', $url);
  }

  /**
   * After-period HITAS apartments that are for sale go to the contact form.
   */
  public function testHitasAfterPeriodApartmentForSaleReturnsContactUrl(): void {
    $project = $this->createProject(
      $this->createOwnershipTerm('Hitas'),
      '2020-01-01T00:00:00',
      '2020-06-01T00:00:00',
      canApplyAfterwards: TRUE,
    );

    $url = $project->getApplicationUrl(NULL, 'APARTMENT_FOR_SALE');

    $this->assertStringContainsString('/contact/', $url);
    $this->assertStringNotContainsString('/application/add/', $url);
  }

  /**
   * After-period HITAS reserved apartments go to the contact form.
   */
  public function testHitasAfterPeriodReservedReturnsContactUrl(): void {
    $project = $this->createProject(
      $this->createOwnershipTerm('Hitas'),
      '2020-01-01T00:00:00',
      '2020-06-01T00:00:00',
      canApplyAfterwards: TRUE,
    );

    $url = $project->getApplicationUrl(NULL, 'RESERVED');

    $this->assertStringContainsString('/contact/', $url);
    $this->assertStringNotContainsString('/application/add/', $url);
  }

  /**
   * After-period HITAS open-for-applications apartments go to contact form.
   */
  public function testHitasAfterPeriodOpenForApplicationsReturnsContactUrl(): void {
    $project = $this->createProject(
      $this->createOwnershipTerm('Hitas'),
      '2020-01-01T00:00:00',
      '2020-06-01T00:00:00',
      canApplyAfterwards: TRUE,
    );

    $url = $project->getApplicationUrl(NULL, 'OPEN_FOR_APPLICATIONS');

    $this->assertStringContainsString('/contact/', $url);
    $this->assertStringNotContainsString('/application/add/', $url);
  }

  /**
   * Apartment node id is appended for HITAS reservation application URLs.
   */
  public function testHitasReservationUrlAppendsApartmentNodeId(): void {
    $project = $this->createProject(
      $this->createOwnershipTerm('Hitas'),
      '2020-01-01T00:00:00',
      '2020-06-01T00:00:00',
      canApplyAfterwards: TRUE,
    );

    $url = $project->getApplicationUrl(NULL, 'FREE_FOR_RESERVATIONS', 84);

    $this->assertStringContainsString('/application/add/hitas/', $url);
    $this->assertStringContainsString('apartment=84', $url);
  }

}
