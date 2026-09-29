<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Response\UserResponse;
use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Entity\ApplicationType;
use Drupal\Core\Form\FormState;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Shared fixtures and helpers for applicant widget kernel tests.
 */
trait ApplicantWidgetKernelTestTrait {

  /**
   * Backend API mock.
   *
   * @var \Drupal\asu_api\Api\BackendApi\BackendApi|\PHPUnit\Framework\MockObject\MockObject
   */
  protected BackendApi $backendApi;

  /**
   * Install shared schemas, application type, customer role and Backend mock.
   */
  protected function setUpApplicantWidgetKernel(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('application_type');
    $this->installConfig(['node']);

    ApplicationType::create([
      'id' => 'hitas',
      'label' => 'Hitas',
    ])->save();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();

    $this->installEntitySchema('asu_application');

    if (!Role::load('customer')) {
      Role::create([
        'id' => 'customer',
        'label' => 'Customer',
      ])->save();
    }

    $this->backendApi = $this->createMock(BackendApi::class);
    $this->container->set('asu_api.backendapi', $this->backendApi);
  }

  /**
   * Create a hitas application owned by the given user.
   *
   * @param \Drupal\user\Entity\User $owner
   *   Application owner.
   * @param array $extraValues
   *   Extra entity values (e.g. main_applicant / applicant).
   *
   * @return \Drupal\asu_application\Entity\Application
   *   Saved application.
   */
  protected function createHitasApplication(User $owner, array $extraValues = []): Application {
    $application = Application::create($extraValues + [
      'bundle' => 'hitas',
      'uid' => $owner->id(),
      'project_id' => 1,
      'status' => 1,
    ]);
    $application->save();

    return $application;
  }

  /**
   * Build a widget form element for an application field.
   *
   * @param \Drupal\asu_application\Entity\Application $application
   *   Application entity.
   * @param string $fieldName
   *   Field name (main_applicant or applicant).
   * @param string $widgetClass
   *   Fully-qualified widget class name.
   * @param string $pluginId
   *   Widget plugin id.
   * @param string $fieldType
   *   Field type id for the plugin definition.
   *
   * @return array
   *   Widget form element.
   */
  protected function buildApplicantFieldWidgetElement(
    Application $application,
    string $fieldName,
    string $widgetClass,
    string $pluginId,
    string $fieldType,
  ): array {
    $items = $application->get($fieldName);
    $widget = $widgetClass::create(
      $this->container,
      [
        'field_definition' => $items->getFieldDefinition(),
        'settings' => [],
        'third_party_settings' => [],
      ],
      $pluginId,
      [
        'id' => $pluginId,
        'field_types' => [$fieldType],
      ]
    );

    $element = [];
    $form = [];
    $form_state = new FormState();

    return $widget->formElement($items, 0, $element, $form, $form_state);
  }

  /**
   * Create a user with the given roles.
   *
   * @param string[] $roles
   *   Role ids to assign.
   * @param string $name
   *   User name.
   *
   * @return \Drupal\user\Entity\User
   *   Created user.
   */
  protected function createUserWithRoles(array $roles, string $name): User {
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@example.com',
      'status' => 1,
    ]);
    foreach ($roles as $role) {
      $user->addRole($role);
    }
    $user->save();

    return $user;
  }

  /**
   * Sample backend profile payload.
   *
   * @param string $firstName
   *   First name.
   * @param string $lastName
   *   Last name.
   *
   * @return array
   *   User information array.
   */
  protected function sampleUserInformation(string $firstName, string $lastName): array {
    return [
      'first_name' => $firstName,
      'last_name' => $lastName,
      'date_of_birth' => '1990-01-15',
      'street_address' => 'Testikatu 1',
      'postal_code' => '00100',
      'city' => 'Helsinki',
      'phone_number' => '0401234567',
      'email' => strtolower($firstName) . '@example.com',
    ];
  }

  /**
   * Expect BackendApi::send to return the given user information.
   *
   * @param array $userInformation
   *   Profile data returned by UserResponse.
   */
  protected function expectBackendUserInformation(array $userInformation): void {
    $response = $this->createMock(UserResponse::class);
    $response->method('getUserInformation')->willReturn($userInformation);
    $this->backendApi->expects($this->once())
      ->method('send')
      ->willReturn($response);
  }

}
