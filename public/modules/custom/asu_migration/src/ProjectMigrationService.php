<?php

namespace Drupal\asu_migration;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\Entity\Node;

/**
 * Migration service for projects and apartments.
 */
class ProjectMigrationService extends AsuMigrationBase {

  /**
   * Apartments csv file.
   *
   * @var resource
   */
  private $file2;

  /**
   * Term storage.
   *
   * @var Drupal\Core\Entity\EntityStorageInterface
   */
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
    $this->termStorage = \Drupal::entityTypeManager()
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

    $errors = $this->migrateProjects();

    fclose($this->file2);

    return $errors;
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

      if (empty($row)) {
        continue;
      }

      if (count($row) != count($headers)) {
        continue;
      }

      $values = array_combine($headers, $row);

      $holdingType = $values['project_holding_type'] == 'RIGHT_OF_RESIDENCE_APARTMENT' ? 'Right of residence apartment' : 'condominium';

      $holdingTypes = $this->termStorage->loadByProperties(['name' => $holdingType]);
      $holdingType = reset($holdingTypes);

      $districts = $this->termStorage->loadByProperties(['name' => $values['project_district']]);
      $district = reset($districts);

      $siteOwners = $this->termStorage->loadByProperties(['name' => $values['project_site_owner']]);
      $siteOwner = reset($siteOwners);

      $ownership = $values['project_ownership_type'] == 'Haso' ? 'HASO' : 'hitas';
      $ownershipTypes = $this->termStorage->loadByProperties(['name' => $ownership]);
      $ownershipType = reset($ownershipTypes);

      $premarketing = \DateTime::createFromFormat('d/m/Y', $values['project_premarketing_start_time'])->format('Y-m-d\T12:00:00');
      $completion = \DateTime::createFromFormat('d/m/Y', $values['project_estimated_completion_date'])->format('Y-m-d');

      $currentProjectId = $values['Taulun avainkenttä (KohdeID)'];
      $project = Node::create([
        'type' => 'project',
        'uuid' => $this->uuidService->createUuid_v5($this->projectUuidNamespace, $currentProjectId),
        'field_housing_company' => $values['project_housing_company'],
        'field_street_address' => $values['project_street_address'],
        'field_postal_code' => $values['project_postal_code'],
        'field_city' => $values['project_city'],
        'field_realty_id' => $values['project_realty_id'],
        'field_construction_year' => $values['project_construction_year'],
        'field_apartment_count' => $values['project_apartment_count'],
        'field_premarketing_start_time' => $premarketing,
        'field_estimated_completion_date' => $completion,
        'field_state_of_sale' => '',
        'status' => 0,
        'field_archived' => 1,
        'author' => 1,
      ]);

      $project->field_holding_type->entity = $holdingType;
      $project->field_ownership_type->entity = $ownershipType;
      $project->field_district->entity = $district;
      $project->field_site_owner->entity = $siteOwner;

      if ($project) {
        if (feof($this->file2)) {
          rewind($this->file2);
        }

        $saleStates = [];
        $apartments = [];
        $apartmentHeaders = NULL;
        foreach ($this->migrateProjectApartments() as $apartmentRow) {
          if (empty($apartmentHeaders)) {
            $apartmentHeaders = $apartmentRow;
            continue;
          }
          if (!is_array($apartmentRow)) {
            continue;
          }
          $apartmentValues = array_combine($apartmentHeaders, $apartmentRow);

          if ($apartmentValues['Vierasavain Project-tauluun (KohdeID)'] !== $currentProjectId) {
            continue;
          }

          $apartmentStateOfSale = $apartmentValues['apartment_state_of_sale'];
          if (!in_array($apartmentStateOfSale, $saleStates)) {
            $saleStates[] = $apartmentStateOfSale;
          }

          $currentApartmentId = $apartmentValues['Taulun avainkenttä (apartment uuid)'];

          $shares = explode('-', $apartmentValues['housing_shares']);

          $apartment = Node::create([
            'type' => 'apartment',
            'status' => $this->apartmentStatusResolver($apartmentStateOfSale),
            'uuid' => $this->uuidService->createUuid_v5($this->apartmentUuidNamespace, $currentApartmentId),
            'title' => $apartmentValues['project_housing_company'] . ' ' . $apartmentValues['apartment_number'],
            'field_apartment_state_of_sale' => strtolower($apartmentStateOfSale),
            'field_apartment_number' => $apartmentValues['apartment_number'],
            'field_stock_start_number' => isset($shares[0]) ? (int) $shares[0] : 0,
            'field_stock_end_number' => isset($shares[1]) ? (int) $shares[1] : 0,
            'field_living_area' => floatval($apartmentValues['living_area']),
            'field_floor' => $apartmentValues['floor'],
            'field_apartment_structure' => $apartmentValues['apartment_structure'],
            'field_sales_price' => $apartmentValues['sales_price'] ? floatval(str_replace(',', '', $apartmentValues['sales_price'])) : 0,
            'field_debt_free_sales_price' => $apartmentValues['debt_free_sales_price'] ? floatval(str_replace(',', '', $apartmentValues['debt_free_sales_price'])) : 0,
            'field_loan_share' => $apartmentValues['loan_share'] ? floatval(str_replace(',', '', $apartmentValues['loan_share'])) : 0,
            'field_price_m2' => $apartmentValues['price_m2'] ? floatval(str_replace(',', '', $apartmentValues['price_m2'])) : 0,
            'field_housing_company_fee' => $apartmentValues['housing_company_fee'] ? floatval(str_replace(',', '', $apartmentValues['housing_company_fee'])) : 0,
            'field_financing_fee' => $apartmentValues['financing_fee'] ? floatval(str_replace(',', '', $apartmentValues['financing_fee'])) : 0,
            'field_financing_fee_m2' => $apartmentValues['financing_fee_m2'] ? floatval(str_replace(',', '', $apartmentValues['financing_fee_m2'])) : 0,
            'field_maintenance_fee' => $apartmentValues['maintenance_fee'] ? floatval(str_replace(',', '', $apartmentValues['maintenance_fee'])) : 0,
            'field_maintenance_fee_m2' => $apartmentValues['maintenance_fee_m2'] ? floatval(str_replace(',', '', $apartmentValues['maintenance_fee_m2'])) : 0,
            'field_right_of_occupancy_payment' => $apartmentValues['right_of_occupancy_payment'] ? floatval(str_replace(',', '', $apartmentValues['right_of_occupancy_payment'])) : 0,
            'field_right_of_occupancy_fee' => $apartmentValues['right_of_occupancy_fee'] ? floatval(str_replace(',', '', $apartmentValues['right_of_occupancy_fee'])) : 0,
            'field_right_of_occupancy_deposit' =>  $apartmentValues['right_of_occupancy_deposit'] ? floatval(str_replace(',', '', $apartmentValues['right_of_occupancy_deposit'])) : 0,
          ]);

          try {
            $apartment->save();
            $apartments[] = $apartment;
          }
          catch (\Exception $e) {
            $errors[] = "Error with the apartment $currentApartmentId of project $currentProjectId: " . $e->getMessage();
          }

        }

        $project->field_apartments = $apartments;
        foreach ($this->projectStateResolver($saleStates) as $field => $value) {
          $project->{$field} = $value;
        }

        try {
          $project->save();
        }
        catch (\Exception $e) {
          $errors[] = "Error with the project $currentProjectId " . $e->getMessage();
        }
      }

    }
    return $errors;
  }

  /**
   * Migrate apartments.
   */
  private function migrateProjectApartments() {
    while (!feof($this->file2)) {
      $row = fgetcsv($this->file2, 4096);
      yield $row;
    }
  }

  /**
   * Project status, archived and state of sale depends on apartments.
   *
   * @param array $salesStates
   *   All states found for project's apartments.
   *
   * @return array
   *   Array of states.
   */
  private function projectStateResolver(array $salesStates) {
    if (in_array('APARTMENT_FOR_SALE', $salesStates)) {
      return [
        'status' => 1,
        'field_archived' => 0,
        'field_state_of_sale' => 'upcoming',
      ];
    }

    if (in_array('OPEN_FOR_APPLICATIONS', $salesStates)) {
      return [
        'status' => 1,
        'field_archived' => 0,
        'field_state_of_sale' => 'for_sale',
      ];
    }

    if (in_array('FREE_FOR_RESERVATIONS', $salesStates)) {
      return [
        'status' => 1,
        'field_archived' => 0,
        'field_state_of_sale' => 'for_sale',
      ];
    }

    if (in_array('RESERVED', $salesStates)) {
      return [
        'status' => 1,
        'field_archived' => 0,
        'field_state_of_sale' => 'processing',
      ];
    }

    if (in_array('RESERVED_HASO', $salesStates)) {
      return [
        'status' => 1,
        'field_archived' => 0,
        'field_state_of_sale' => 'processing',
      ];
    }

    if (in_array('SOLD', $salesStates)) {
      return [
        'status' => 0,
        'field_archived' => 1,
        'field_state_of_sale' => 'ready',
      ];
    }
  }

  /**
   * Apartment status depends on sale-state.
   *
   * @param string $saleState
   *   State of sale.
   *
   * @return int
   *   True or false.
   */
  private function apartmentStatusResolver(string $saleState) {
    if ($saleState === 'SOLD') {
      return 0;
    }
    return 1;
  }

}
