<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Entity\ApplicationType;
use Drupal\asu_application\Form\SalespersonApplicationForm;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests salesperson application form project labels.
 *
 * @group asu_application
 *
 * @coversDefaultClass \Drupal\asu_application\Form\SalespersonApplicationForm
 */
final class SalespersonApplicationFormTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('application_type');
    $this->installConfig(['node']);
    $this->installSalespersonProjectContentModel();

    ApplicationType::create([
      'id' => 'hitas',
      'label' => 'Hitas',
    ])->save();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();

    $this->installEntitySchema('asu_application');

    $date_formatter = $this->createMock(DateFormatterInterface::class);
    $date_formatter->method('format')->willReturn('1.1.2026');
    $this->container->set('date.formatter', $date_formatter);
  }

  /**
   * Tests that applications for non-selectable projects still show labels.
   */
  public function testApplicationListShowsLabelsForProjectsOutsideDropdown(): void {
    $ready = $this->createTestProject('Ready Co', 'Ready St 1', 'ready');
    $upcoming = $this->createTestProject('Upcoming Co', 'Upcoming St 2', 'upcoming');
    $user = $this->createCustomerUser();
    $this->container->get('current_user')->setAccount($user);

    $application = Application::create([
      'bundle' => 'hitas',
      'uid' => $user->id(),
      'project_id' => (int) $upcoming->id(),
      'status' => 1,
    ]);
    $application->save();

    $form = $this->container->get('form_builder')->getForm(
      SalespersonApplicationForm::class,
      (string) $user->id(),
    );

    $options = $form['projects']['#options'];
    $this->assertArrayHasKey((int) $ready->id(), $options);
    $this->assertArrayNotHasKey((int) $upcoming->id(), $options);
    $this->assertSame('Ready Co, Ready St 1', $options[(int) $ready->id()]);
    $this->assertSame('Select project', (string) $form['projects']['#empty_option']);

    $application_markup = (string) $form['user_applications_' . $application->id()]['#markup'];
    $this->assertStringContainsString('Upcoming Co, Upcoming St 2', $application_markup);
    $this->assertStringNotContainsString('Unknown project', $application_markup);
    $this->assertStringContainsString('/application/' . $application->id() . '/edit', $application_markup);
    $this->assertStringContainsString('>Edit<', $application_markup);
  }

  /**
   * Tests that late applications are marked in the applications list.
   */
  public function testApplicationListMarksLateApplications(): void {
    $this->installApplicationEndTimeField();

    $project = $this->createTestProject(
      'Ready Co',
      'Ready St 1',
      'ready',
      '2020-01-01T00:00:00',
    );

    $user = $this->createCustomerUser();
    $this->container->get('current_user')->setAccount($user);

    $application = Application::create([
      'bundle' => 'hitas',
      'uid' => $user->id(),
      'project_id' => (int) $project->id(),
      'status' => 1,
    ]);
    $application->save();

    $form = $this->container->get('form_builder')->getForm(
      SalespersonApplicationForm::class,
      (string) $user->id(),
    );

    $application_markup = (string) $form['user_applications_' . $application->id()]['#markup'];
    $this->assertStringContainsString('(after-application)', $application_markup);
    $this->assertStringContainsString('/application/' . $application->id() . '/edit', $application_markup);
  }

  /**
   * Tests that sent applications also link to the edit page.
   */
  public function testApplicationListLinksLockedApplicationsToEdit(): void {
    $project = $this->createTestProject('Ready Co', 'Ready St 1', 'ready');
    $user = $this->createCustomerUser();
    $this->container->get('current_user')->setAccount($user);

    $application = Application::create([
      'bundle' => 'hitas',
      'uid' => $user->id(),
      'project_id' => (int) $project->id(),
      'status' => 1,
    ]);
    $application->set('field_locked', 1);
    $application->save();

    $form = $this->container->get('form_builder')->getForm(
      SalespersonApplicationForm::class,
      (string) $user->id(),
    );

    $application_markup = (string) $form['user_applications_' . $application->id()]['#markup'];
    $this->assertStringContainsString('/application/' . $application->id() . '/edit', $application_markup);
    $this->assertStringContainsString('>Edit<', $application_markup);
  }

}
