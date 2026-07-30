<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\asu_application\Plugin\Field\FieldWidget\ApplicantWidget;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests additional applicant widget default values.
 *
 * @group asu_application
 *
 * @coversDefaultClass \Drupal\asu_application\Plugin\Field\FieldWidget\ApplicantWidget
 */
final class ApplicantWidgetTest extends KernelTestBase {

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
    $this->installCoApplicantMapTable();
    $this->installSamlHashField();
    $this->setUpApplicantWidgetKernel();
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
    $application = $this->createHitasApplication($owner);
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
    $application = $this->createHitasApplication($owner);
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
    $application = $this->createHitasApplication($owner, [
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
    $application = $this->createHitasApplication($owner);

    $this->container->get('current_user')->setAccount($salesperson);
    $this->backendApi->expects($this->never())->method('send');

    $element = $this->buildWidgetElement($application);

    $this->assertFalse((bool) $element['has_additional_applicant']['#default_value']);
    $this->assertSame('', $element['first_name']['#default_value']);
    $this->assertSame('', $element['last_name']['#default_value']);
  }

  /**
   * Build applicant widget form element.
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
      'applicant',
      ApplicantWidget::class,
      'asu_applicant_widget',
      'asu_applicant',
    );
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
      'description' => 'Maps application id to co-applicant SAML hash.',
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
