<?php

namespace Drupal\asu_migration;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

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
   * Get migration files from server tmp.
   */
  private function getFilesFromTmp() {
    if (!file_exists($this->projectFilePath)) {
      return ['Project file is missing!'];
    }

    if (!file_exists($this->apartmentFilePath)) {
      return ['Apartment file is missing!'];
    }

    $this->file = fopen($this->projectFilePath, 'r');
    $this->file2 = fopen($this->apartmentFilePath, 'r');
  }

  /**
   * Get migration files from local.
   */
  private function getFilesFromLocal() {
    $this->file = fopen('/app/migrations/projects.csv', 'r');
    $this->file2 = fopen('/app/migrations/apartments.csv', 'r');
  }

  /**
   * Run migrations.
   */
  public function migrate(): array {
    getFilesFromTmp();

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

      $holdingTypes = $this->termStorage->loadByProperties(
        [
          'name' => $holdingType,
          'vid' => 'holding_type',
        ]
      );
      $holdingType = $this->getTerm($holdingTypes, $holdingType, 'holding_type');

      $districts = $this->termStorage->loadByProperties(
        [
          'name' => $values['project_district'],
          'vid' => 'districts',
        ]
      );
      $district = $this->getTerm($districts, $values['project_district'], 'districts');

      $siteOwners = $this->termStorage->loadByProperties(
        [
          'name' => $values['project_site_owner'],
          'vid' => 'site_owners',
        ]
      );
      $siteOwner = $this->getTerm($siteOwners, $values['project_site_owner'], 'site_owners');

      $ownership = $values['project_ownership_type'] == 'Haso' ? 'HASO' : 'hitas';
      $ownershipTypes = $this->termStorage->loadByProperties(
        [
          'name' => $ownership,
          'vid' => 'ownership_type',
        ]
      );
      $ownershipType = $this->getTerm($ownershipTypes, $ownership, 'ownership_type');

      $premarketing = NULL;
      if (isset($values['project_premarketing_start_time']) && !empty($values['project_premarketing_start_time'])) {
        $premarketing = (\DateTime::createFromFormat('d.m.Y H:i:s', $values['project_premarketing_start_time']))->format('Y-m-d\T12:00:00');
      }

      $completion = NULL;
      if (isset($values['project_estimated_completion_date']) && !empty($values['project_estimated_completion_date'])) {
        $completion = (\DateTime::createFromFormat('d.m.Y H:i:s', $values['project_estimated_completion_date']))->format('Y-m-d');
      }

      $currentProjectId = $values['Taulun avainkenttä (KohdeID)'];
      $project = Node::create([
        'type' => 'project',
        'uuid' => $this->uuidService->createUuidV5($this->projectUuidNamespace, $currentProjectId),
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

      if (!$holdingType || !$ownershipType || !$siteOwner) {
        $errors[] = "Error with the project $currentProjectId. Error in create term.";
        break;
      }
      else {
        $project->field_holding_type->entity = $holdingType;
        $project->field_ownership_type->entity = $ownershipType;
        $project->field_site_owner->entity = $siteOwner;
      }

      if (!$district) {
        $errors[] = "Error with the project $currentProjectId. Error in create district term.";
        continue;
      }
      else {
        $project->field_district->entity = $district;
      }

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
            'uuid' => $this->uuidService->createUuidV5($this->apartmentUuidNamespace, $currentApartmentId),
            'title' => $apartmentValues['project_housing_company'] . ' ' . $apartmentValues['apartment_number'],
            'field_apartment_state_of_sale' => strtolower($apartmentStateOfSale),
            'field_apartment_number' => $apartmentValues['apartment_number'],
            'field_stock_start_number' => isset($shares[0]) ? (int) $shares[0] : 0,
            'field_stock_end_number' => isset($shares[1]) ? (int) $shares[1] : 0,
            'field_living_area' => floatval($apartmentValues['living_area']),
            'field_floor' => $apartmentValues['floor'],
            'field_apartment_structure' => $apartmentValues['apartment_structure'],
            'field_sales_price' => $this->handleFloat($apartmentValues['sales_price']),
            'field_debt_free_sales_price' => $this->handleFloat($apartmentValues['debt_free_sales_price']),
            'field_loan_share' => $this->handleFloat($apartmentValues['loan_share']),
            'field_price_m2' => $this->handleFloat($apartmentValues['price_m2']),
            'field_housing_company_fee' => $this->handleFloat($apartmentValues['housing_company_fee']),
            'field_financing_fee' => $this->handleFloat($apartmentValues['financing_fee']),
            'field_financing_fee_m2' => $this->handleFloat($apartmentValues['financing_fee_m2']),
            'field_maintenance_fee' => $this->handleFloat($apartmentValues['maintenance_fee']),
            'field_maintenance_fee_m2' => $this->handleFloat($apartmentValues['maintenance_fee_m2']),
            'field_right_of_occupancy_payment' => $this->handleFloat($apartmentValues['right_of_occupancy_payment']),
            'field_right_of_occupancy_fee' => $this->handleFloat($apartmentValues['right_of_occupancy_fee']),
            'field_right_of_occupancy_deposit' => $this->handleFloat($apartmentValues['right_of_occupancy_deposit']),
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
      $row = fgetcsv($this->file2, 4096, ';');
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

  /**
   * Create terms.
   *
   * @param array $terms
   *   Terms.
   * @param string $term
   *   Term name value.
   * @param string $vid
   *   Term vid value.
   */
  private function getTerm(array $terms, string $term, string $vid) {
    if (!empty($terms)) {
      return reset($terms);
    }

    if (preg_match('~[0-9]+~', $term)) {
      return FALSE;
    }

    try {
      $new_term = Term::create([
        'name' => $term,
        'vid' => $vid,
      ]);
      $new_term->save();
    }
    catch (\Exception $exception) {
      return FALSE;
    }

    return $new_term;
  }

  /**
   * Custom Handle float.
   *
   * @param string $value
   *   Float value.
   *
   * @return float|int
   *   Handled value.
   */
  private function handleFloat(string $value) {
    if (!$value) {
      return 0;
    }

    return floatval(str_replace(',', '.', $value));
  }

}
