<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Entity\ApplicationType;
use Drupal\asu_application\Service\LateApplicationChecker;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests late-application detection for salesperson form labels.
 *
 * @group asu_application
 *
 * @coversDefaultClass \Drupal\asu_application\Service\LateApplicationChecker
 */
final class LateApplicationCheckerTest extends KernelTestBase {

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
    'datetime',
    'asu_api',
    'asu_application',
  ];

  /**
   * The checker under test.
   *
   * @var \Drupal\asu_application\Service\LateApplicationChecker
   */
  private LateApplicationChecker $checker;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('application_type');
    $this->installConfig(['node']);
    $this->installSalespersonProjectContentModel();
    $this->installApplicationEndTimeField();

    ApplicationType::create([
      'id' => 'hitas',
      'label' => 'Hitas',
    ])->save();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $this->installEntitySchema('asu_application');

    $this->checker = $this->container->get('asu_application.late_application_checker');
  }

  /**
   * Tests that applications created after the end time are late submissions.
   */
  public function testIsLateSubmissionWhenCreatedAfterApplicationEnd(): void {
    $user = $this->createCustomerUser();
    $this->container->get('current_user')->setAccount($user);

    $project = $this->createTestProject(
      'Ready Co',
      'Ready St 1',
      'ready',
      '2020-01-01T00:00:00',
    );

    $application = Application::create([
      'bundle' => 'hitas',
      'uid' => $user->id(),
      'project_id' => (int) $project->id(),
      'status' => 1,
    ]);
    $application->save();

    $this->assertTrue($this->checker->isLateSubmission($application));
  }

  /**
   * Tests that applications created before the end time are not late.
   */
  public function testIsLateSubmissionFalseWhenCreatedBeforeApplicationEnd(): void {
    $user = $this->createCustomerUser();
    $this->container->get('current_user')->setAccount($user);

    $project = $this->createTestProject(
      'Ready Co',
      'Ready St 1',
      'ready',
      '2099-01-01T00:00:00',
    );

    $application = Application::create([
      'bundle' => 'hitas',
      'uid' => $user->id(),
      'project_id' => (int) $project->id(),
      'status' => 1,
    ]);
    $application->save();

    $this->assertFalse($this->checker->isLateSubmission($application));
  }

}
