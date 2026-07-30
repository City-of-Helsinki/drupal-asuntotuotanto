<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Response\UserResponse;
use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Entity\ApplicationType;
use Drupal\asu_application\Plugin\Field\FieldWidget\ApplicantWidget;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests additional applicant widget default values.
 *
 * @group asu_application
 *
 * @coversDefaultClass \Drupal\asu_application\Plugin\Field\FieldWidget\ApplicantWidget
 */
final class ApplicantWidgetTest extends KernelTestBase {

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
   * Backend API mock.
   *
   * @var \Drupal\asu_api\Api\BackendApi\BackendApi|\PHPUnit\Framework\MockObject\MockObject
   */
  private BackendApi $backendApi;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('application_type');
    $this->installConfig(['node']);
    $this->installCoApplicantMapTable();
    $this->installSamlHashField();

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
   * Prefills additional applicant from mapped co-applicant for sales.
   *
   * - Application applicant field is empty (e.g. after sensitive data cleanup).
   * - Co-applicant map points at a customer user SAML hash.
   * - Current user is a salesperson.
   * - Backend profile for the co-applicant is returned.
   * - Widget defaults and checkbox come from that profile.
   */
  public function testPrefillsMappedCoApplicantWhenSalespersonOpensApplication(): void {
    $owner = $this->createUserWithRoles(['customer'], 'owner-customer');
    $coApplicant = $this->createUserWithRoles(['customer'], 'co-applicant');
    $coApplicant->set('field_saml_hash', 'co-applicant-hash');
    $coApplicant->save();
    $salesperson = $this->createUserWithRoles([], 'sales-user');

    $this->container->get('current_user')->setAccount($owner);
    $application = Application::create([
      'bundle' => 'hitas',
      'uid' => $owner->id(),
      'project_id' => 1,
      'status' => 1,
    ]);
    $application->save();
    $this->insertCoApplicantMapping((int) $application->id(), 'co-applicant-hash');

    $this->container->get('current_user')->setAccount($salesperson);
    $this->expectBackendUserInformation($this->sampleUserInformation('Aino', 'Lisahakija'));

    $element = $this->buildWidgetElement($application);

    $this->assertTrue((bool) $element['has_additional_applicant']['#default_value']);
    $this->assertSame('Aino', $element['first_name']['#default_value']);
    $this->assertSame('Lisahakija', $element['last_name']['#default_value']);
    $this->assertSame('aino@example.com', $element['email']['#default_value']);
    $this->assertSame('Testikatu 1', $element['address']['#default_value']);
  }

  /**
   * Prefills mapped co-applicant when the application owner opens the form.
   *
   * - Applicant field empty, co-applicant map present.
   * - Owner is the current user.
   * - Widget defaults come from the mapped co-applicant Backend profile.
   */
  public function testPrefillsMappedCoApplicantWhenOwnerOpensApplication(): void {
    $owner = $this->createUserWithRoles(['customer'], 'owner-self');
    $coApplicant = $this->createUserWithRoles(['customer'], 'co-applicant-self');
    $coApplicant->set('field_saml_hash', 'owner-co-hash');
    $coApplicant->save();

    $this->container->get('current_user')->setAccount($owner);
    $application = Application::create([
      'bundle' => 'hitas',
      'uid' => $owner->id(),
      'project_id' => 1,
      'status' => 1,
    ]);
    $application->save();
    $this->insertCoApplicantMapping((int) $application->id(), 'owner-co-hash');

    $this->expectBackendUserInformation($this->sampleUserInformation('Kerttu', 'Kaveri'));

    $element = $this->buildWidgetElement($application);

    $this->assertTrue((bool) $element['has_additional_applicant']['#default_value']);
    $this->assertSame('Kerttu', $element['first_name']['#default_value']);
    $this->assertSame('Kaveri', $element['last_name']['#default_value']);
  }

  /**
   * Stored applicant values take precedence over Backend profile data.
   *
   * - Application already has applicant values stored.
   * - Co-applicant map and Backend profile would return different values.
   * - Widget keeps the stored values.
   */
  public function testStoredValuesTakePrecedenceOverBackendProfile(): void {
    $owner = $this->createUserWithRoles(['customer'], 'owner-stored');
    $coApplicant = $this->createUserWithRoles(['customer'], 'co-stored');
    $coApplicant->set('field_saml_hash', 'stored-co-hash');
    $coApplicant->save();

    $this->container->get('current_user')->setAccount($owner);
    $application = Application::create([
      'bundle' => 'hitas',
      'uid' => $owner->id(),
      'project_id' => 1,
      'status' => 1,
      'applicant' => [
        [
          'first_name' => 'Stored',
          'last_name' => 'Applicant',
          'date_of_birth' => '1988-03-03',
          'personal_id' => 'B4321',
          'address' => 'Stored Street 9',
          'postal_code' => '00300',
          'city' => 'Vantaa',
          'phone' => '0509998888',
          'email' => 'stored-applicant@example.com',
        ],
      ],
    ]);
    $application->save();
    $this->insertCoApplicantMapping((int) $application->id(), 'stored-co-hash');

    $this->backendApi->expects($this->never())->method('send');

    $element = $this->buildWidgetElement($application);

    $this->assertTrue((bool) $element['has_additional_applicant']['#default_value']);
    $this->assertSame('Stored', $element['first_name']['#default_value']);
    $this->assertSame('Applicant', $element['last_name']['#default_value']);
    $this->assertSame('stored-applicant@example.com', $element['email']['#default_value']);
  }

  /**
   * Leaves additional applicant empty when there is no co-applicant mapping.
   *
   * - Applicant field empty and no co-applicant map row.
   * - Backend API is not called.
   * - Checkbox stays unchecked and fields stay empty.
   */
  public function testLeavesEmptyWhenNoAdditionalApplicantExists(): void {
    $owner = $this->createUserWithRoles(['customer'], 'owner-none');
    $salesperson = $this->createUserWithRoles([], 'sales-none');

    $this->container->get('current_user')->setAccount($owner);
    $application = Application::create([
      'bundle' => 'hitas',
      'uid' => $owner->id(),
      'project_id' => 1,
      'status' => 1,
    ]);
    $application->save();

    $this->container->get('current_user')->setAccount($salesperson);
    $this->backendApi->expects($this->never())->method('send');

    $element = $this->buildWidgetElement($application);

    $this->assertFalse((bool) $element['has_additional_applicant']['#default_value']);
    $this->assertSame('', $element['first_name']['#default_value']);
    $this->assertSame('', $element['last_name']['#default_value']);
  }

  /**
   * Build widget form element for the application applicant field.
   *
   * @param \Drupal\asu_application\Entity\Application $application
   *   Application entity.
   *
   * @return array
   *   Widget form element.
   */
  private function buildWidgetElement(Application $application): array {
    $items = $application->get('applicant');
    $field_definition = $items->getFieldDefinition();
    $widget = ApplicantWidget::create(
      $this->container,
      [
        'field_definition' => $field_definition,
        'settings' => [],
        'third_party_settings' => [],
      ],
      'asu_applicant_widget',
      [
        'id' => 'asu_applicant_widget',
        'field_types' => ['asu_applicant'],
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
  private function createUserWithRoles(array $roles, string $name): User {
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
  private function sampleUserInformation(string $firstName, string $lastName): array {
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
  private function expectBackendUserInformation(array $userInformation): void {
    $response = $this->createMock(UserResponse::class);
    $response->method('getUserInformation')->willReturn($userInformation);
    $this->backendApi->expects($this->once())
      ->method('send')
      ->willReturn($response);
  }

  /**
   * Insert a co-applicant mapping row.
   *
   * @param int $applicationId
   *   Application id.
   * @param string $samlHash
   *   Co-applicant SAML hash.
   */
  private function insertCoApplicantMapping(int $applicationId, string $samlHash): void {
    $time = $this->container->get('datetime.time')->getRequestTime();
    $this->container->get('database')->insert('asu_application_co_applicant_map')
      ->fields([
        'application_id' => $applicationId,
        'co_applicant_saml_hash' => $samlHash,
        'created' => $time,
        'changed' => $time,
      ])
      ->execute();
  }

  /**
   * Create co-applicant mapping table used by the widget.
   */
  private function installCoApplicantMapTable(): void {
    $schema = $this->container->get('database')->schema();
    if ($schema->tableExists('asu_application_co_applicant_map')) {
      return;
    }

    $schema->createTable('asu_application_co_applicant_map', [
      'description' => 'Maps application id to co-applicant SAML hash for access checks.',
      'fields' => [
        'application_id' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'co_applicant_saml_hash' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
        ],
        'created' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
        'changed' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
      ],
      'primary key' => ['application_id'],
      'indexes' => [
        'co_applicant_saml_hash' => ['co_applicant_saml_hash'],
      ],
    ]);
  }

  /**
   * Install field_saml_hash on user entities.
   */
  private function installSamlHashField(): void {
    if (!FieldStorageConfig::loadByName('user', 'field_saml_hash')) {
      FieldStorageConfig::create([
        'field_name' => 'field_saml_hash',
        'entity_type' => 'user',
        'type' => 'string',
      ])->save();
    }

    if (!FieldConfig::loadByName('user', 'user', 'field_saml_hash')) {
      FieldConfig::create([
        'field_name' => 'field_saml_hash',
        'entity_type' => 'user',
        'bundle' => 'user',
        'label' => 'SAML hash',
      ])->save();
    }
  }

}
