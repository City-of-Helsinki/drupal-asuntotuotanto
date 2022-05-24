<?php

namespace Drupal\asu_migration;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\Entity\Node;

/**
 * Migration service for projects and apartments.
 */
class ProjectMigrationService extends AsuMigrationBase {

  private $file2;

  private EntityStorageInterface $termStorage;

  /**
   * Construct.
   */
  public function __construct(
    UuidService $uuidService,
    BackendApi $backendApi,
    private string $projectFilePath,
    private string $apartmentFilePath,
    private string $projectUuidNamespace,
    private string $apartmentUuidNamespace,
  ) {
    parent::__construct($uuidService, $backendApi);
    $this->entityStorage = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term');
  }

  /**
   * Run migrations.
   */
  public function migrate(): array {
    if (!file_exists($this->projectFilePath)) {
      return ['Project file is missing!'];
    }

    if (!file_exists($this->apartmentFilePath)) {
      return ['Apartment file is missing!'];
    }

    $this->file = fopen($this->projectFilePath, 'r');
    $this->file2 = fopen($this->apartmentFilePath, 'r');
    $projects = $this->migrateProjects();

    fclose($this->file2);

    return [];
  }

  /**
   * Migrate projects.
   */
  private function migrateProjects(): array {
    $errors = [];
    $headers = [];

    foreach ($this->rows() as $row) {
      if (empty($headers)) {
        $headers = $row;
        continue;
      }
      $values = array_combine($headers, $row);

      $holdingTypes = $this->termStorage->loadByProperties(['name' => $values['project_housing_company']]);
      $holdingType = reset($holdingTypes);

      $districts = $this->termStorage->loadByProperties(['name' => $values['project_district']]);
      $district = reset($districts);

      $siteOwners = $this->termStorage->loadByProperties(['name' => $values['project_site_owner']]);
      $siteOwner = reset($siteOwners);

      $ownershipTypes = $this->termStorage->loadByProperties(['name' => $values['project_site_owner']]);
      $ownershipType = reset($ownershipTypes);

      $premarketing = (new \DateTime($values['project_premarketing_start_time']))->format('Y-m-d');
      $completion = (new \DateTime($values['project_estimated_completion_date']))->format('Y-m-d');

      $currentProjectId = $values['Taulun avainkenttä (KohdeID)'];
      $project = Node::create([
        'type' => 'project',
        'uuid' => $this->uuidService->createUuid_v5($this->projectUuidNamespace, $currentProjectId),
        'field_housing_company' => $values['project_housing_company'],
        'field_holding_type' => [$holdingType], // enum -> text
        'field_street_address' => $values['project_street_address'],
        'field_postal_code' => $values['project_postal_code'],
        'field_city' => $values['project_city'],
        'field_realty_id' => $values['project_realty_id'],
        'field_construction_year' => $values['project_construction_year'],
        'field_apartment_count' => $values['project_apartment_count'],
        'field_premarketing_start_time' => $premarketing,
        'field_estimated_completion_date' => $completion,
        'field_state_of_sale' => ''
        'status' => 0,
        'field_archived' => 1
      ]);
      $project->field_ownership_type->entity = $ownershipType;
      $project->field_district->entity = $district;
      $project->field_site_owner->entity = $siteOwner;
      $status = $project->save();

      $apartments = [];
      if ($status === 1) {
        // $project->id;

        $apartmentHeaders = [];
        $terms = [];
        $apartments = [];

        foreach($this->migrateProjectApartments() as $apartmentRow){
          if (empty($apartmentHeaders)) {
            $this->apartmentHeaders = $apartmentRow;
          }
          $apartmentValues = array_combine($headers, $apartmentRow);

          if ($apartmentValues['Vierasavain Project-tauluun (KohdeID)'] !== $currentProjectId) {
            continue;
          }

          $shares = explode(' - ', $apartmentValues['housing_shares']);

          $ownership_type = $this->termStorage->loadByProperties($apartmentValues['project_ownership_type']);
          $state_of_sale = $this->termStorage->loadByProperties();

          $apartment = Node::create([
            'type' => 'apartment',
            'status' => 0,
            'uuid' => $this->uuidService->createUuid_v5($this->apartmentUuidNamespace, $apartmentValues['Taulun avainkenttä (apartment uuid)']),
            'title' => $apartmentValues['project_housing_company'],
            'field_ownership_type' => $apartmentValues['project_ownership_type'], //
            'field_state_of_sale' => $apartmentValues['apartment_state_of_sale'], //
            'field_apartment_number' => $apartmentValues['apartment_number'],
            'field_stock_start_number' => (int)$shares[0],
            'field_stock_end_number' => (int)$shares[1],
            'field_living_area' => $apartmentValues['living_area'],
            'field_floor' => $apartmentValues['floor'],
            'field_apartment_structure' => $apartmentValues['apartment_structure'],
            'field_sales_price' => $apartmentValues['sales_price'],
            'field_debt_free_sales_price' => $apartmentValues['debt_free_sales_price'],
            'field_loan_share' => $apartmentValues['loan_share'],
            'field_price_m2' => $apartmentValues['price_m2'],
            'field_housing_company_fee' => $apartmentValues['housing_company_fee'],
            'field_financing_fee' => $apartmentValues['financing_fee'],
            'field_financing_fee_m2' => $apartmentValues['financing_fee_m2'],
            'field_maintenance_fee' => $apartmentValues['maintenance_fee'],
            'field_maintenance_fee_m2' => $apartmentValues['maintenance_fee_m2'],
            'field_right_of_occupancy_fee' => $apartmentValues['right_of_occupancy_fee'],
            'field_right_of_occupancy_deposit' => $apartmentValues['right_of_occupancy_deposit'],
            'field_right_of_occupancy_payment' => $apartmentValues['right_of_occupancy_payment'],
          ]);

        }

      }
    }
  }

  /**z
   * Migrate apartments.
   */
  private function migrateProjectApartments() {
    if () {

    }
  }



}
