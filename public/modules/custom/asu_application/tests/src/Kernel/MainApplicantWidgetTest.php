<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\asu_application\Plugin\Field\FieldWidget\MainApplicantWidget;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests main applicant widget default values.
 *
 * @group asu_application
 *
 * @coversDefaultClass \Drupal\asu_application\Plugin\Field\FieldWidget\MainApplicantWidget
 */
final class MainApplicantWidgetTest extends KernelTestBase {

  use ApplicantWidgetKernelTestTrait;

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
    'datetime',
    'asu_api',
    'asu_application',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpApplicantWidgetKernel();
  }

  /**
   * Prefills from application owner when a salesperson opens the form.
   *
   * - Application owner is a customer with empty main_applicant values.
   * - Current user is a salesperson (no customer role).
   * - Backend profile for the owner is returned by the API.
   * - Widget default values come from the owner profile.
   */
  public function testPrefillsOwnerDataWhenSalespersonOpensApplication(): void {
    $owner = $this->createUserWithRoles(['customer'], 'owner-customer');
    $salesperson = $this->createUserWithRoles([], 'sales-user');
    $this->container->get('current_user')->setAccount($owner);

    $application = $this->createHitasApplication($owner);

    $this->container->get('current_user')->setAccount($salesperson);

    $ownerProfile = $this->sampleUserInformation('Maija', 'Meikalainen');
    $this->expectBackendUserInformation($ownerProfile);

    $element = $this->buildWidgetElement($application);

    $this->assertSame('Maija', $element['first_name']['#default_value']);
    $this->assertSame('Meikalainen', $element['last_name']['#default_value']);
    $this->assertSame('1990-01-15', $element['date_of_birth']['#default_value']);
    $this->assertSame('Testikatu 1', $element['address']['#default_value']);
    $this->assertSame('00100', $element['postal_code']['#default_value']);
    $this->assertSame('Helsinki', $element['city']['#default_value']);
    $this->assertSame('0401234567', $element['phone']['#default_value']);
    $this->assertSame('maija@example.com', $element['email']['#default_value']);
  }

  /**
   * Prefills from the current customer when they open their own application.
   *
   * - Current user owns the application and has the customer role.
   * - Backend profile for the current user is returned.
   * - Widget default values come from that profile.
   */
  public function testPrefillsCurrentCustomerWhenOwnerOpensApplication(): void {
    $owner = $this->createUserWithRoles(['customer'], 'owner-self');
    $this->container->get('current_user')->setAccount($owner);

    $application = $this->createHitasApplication($owner);

    $ownerProfile = $this->sampleUserInformation('Pekka', 'Asiakas');
    $this->expectBackendUserInformation($ownerProfile);

    $element = $this->buildWidgetElement($application);

    $this->assertSame('Pekka', $element['first_name']['#default_value']);
    $this->assertSame('Asiakas', $element['last_name']['#default_value']);
    $this->assertSame('pekka@example.com', $element['email']['#default_value']);
  }

  /**
   * Stored main_applicant values take precedence over backend profile data.
   *
   * - Application already has main_applicant first/last name stored.
   * - Backend would return different values.
   * - Widget keeps the stored values.
   */
  public function testStoredValuesTakePrecedenceOverBackendProfile(): void {
    $owner = $this->createUserWithRoles(['customer'], 'owner-stored');
    $this->container->get('current_user')->setAccount($owner);

    $application = $this->createHitasApplication($owner, [
      'main_applicant' => [
        [
          'first_name' => 'Stored',
          'last_name' => 'Name',
          'date_of_birth' => '1985-05-05',
          'personal_id' => 'A1234',
          'address' => 'Stored Street 2',
          'postal_code' => '00200',
          'city' => 'Espoo',
          'phone' => '0501112222',
          'email' => 'stored@example.com',
        ],
      ],
    ]);

    $this->expectBackendUserInformation(
      $this->sampleUserInformation('Backend', 'Profile')
    );

    $element = $this->buildWidgetElement($application);

    $this->assertSame('Stored', $element['first_name']['#default_value']);
    $this->assertSame('Name', $element['last_name']['#default_value']);
    $this->assertSame('stored@example.com', $element['email']['#default_value']);
  }

  /**
   * Backend fetch failure still returns a form element array.
   *
   * - Salesperson opens a customer-owned application.
   * - Backend API throws while loading the owner profile.
   * - formElement returns a render array, not a Response.
   * - Default values stay empty when the profile cannot be loaded.
   */
  public function testBackendFailureStillReturnsFormElementArray(): void {
    $owner = $this->createUserWithRoles(['customer'], 'owner-api-fail');
    $salesperson = $this->createUserWithRoles([], 'sales-api-fail');
    $this->container->get('current_user')->setAccount($owner);

    $application = $this->createHitasApplication($owner);

    $this->container->get('current_user')->setAccount($salesperson);
    $this->backendApi->expects($this->once())
      ->method('send')
      ->willThrowException(new \Exception('Backend unavailable'));

    $element = $this->buildWidgetElement($application);

    $this->assertIsArray($element);
    $this->assertArrayHasKey('first_name', $element);
    $this->assertArrayHasKey('#default_value', $element['first_name']);
    $this->assertNull($element['first_name']['#default_value']);
    $this->assertNull($element['last_name']['#default_value']);
    $this->assertNull($element['email']['#default_value']);
  }

  /**
   * Build main_applicant widget form element.
   *
   * @param \Drupal\asu_application\Entity\Application $application
   *   Application entity.
   *
   * @return array
   *   Widget form element.
   */
  private function buildWidgetElement($application): array {
    return $this->buildApplicantFieldWidgetElement(
      $application,
      'main_applicant',
      MainApplicantWidget::class,
      'asu_main_applicant_widget',
      'asu_main_applicant',
    );
  }

}
