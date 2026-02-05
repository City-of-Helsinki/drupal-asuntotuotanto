<?php

declare(strict_types=1);

namespace Drupal\asu_rest\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\node\Entity\Node;

/**
 * Search service to filter apartments and projects for REST endpoints.
 */
final class SearchService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Search apartments using the same filter semantics as the old ES endpoint.
   *
   * @param array $params
   *   Query parameters.
   * @param int|null $projectId
   *   Optional project id to limit results to.
   * @param int $offset
   *   Offset for pagination.
   * @param int $limit
   *   Maximum number of results.
   *
   * @return array{total:int,items:\Drupal\node\Entity\Node[]}
   *   Filtered and paginated results.
   */
  public function searchApartments(array $params, ?int $projectId, int $offset, int $limit): array {
    $matches = $this->filterApartments($params, $projectId);
    $total = count($matches);
    $items = array_slice($matches, $offset, $limit);

    return [
      'total' => $total,
      'items' => $items,
    ];
  }

  /**
   * Search projects using apartment-based filtering.
   *
   * @param array $params
   *   Query parameters.
   * @param int $offset
   *   Offset for pagination.
   * @param int $limit
   *   Maximum number of results.
   *
   * @return array{total:int,items:\Drupal\node\Entity\Node[]}
   *   Filtered and paginated projects.
   */
  public function searchProjects(array $params, int $offset, int $limit): array {
    if (!$this->hasApartmentFilters($params)) {
      $projects = $this->filterProjects($params);
    }
    else {
      $apartments = $this->filterApartments($params, NULL);
      $projects = [];

      foreach ($apartments as $apartment) {
        if ($project = $this->getProjectForApartment($apartment)) {
          $projects[$project->id()] = $project;
        }
      }

      $projects = array_values($projects);
    }
    $total = count($projects);
    $items = array_slice($projects, $offset, $limit);

    return [
      'total' => $total,
      'items' => $items,
    ];
  }

  /**
   * Load a single apartment.
   */
  public function loadApartment(int $apartmentId): ?Node {
    $apartment = $this->entityTypeManager->getStorage('node')->load($apartmentId);
    if ($apartment instanceof Node && $apartment->bundle() === 'apartment') {
      return $apartment;
    }
    return NULL;
  }

  /**
   * Load a single project.
   */
  public function loadProject(int $projectId): ?Node {
    $project = $this->entityTypeManager->getStorage('node')->load($projectId);
    if ($project instanceof Node && $project->bundle() === 'project') {
      return $project;
    }
    return NULL;
  }

  /**
   * Filter apartments and return the matched nodes.
   *
   * @param array $params
   *   Query parameters.
   * @param int|null $projectId
   *   Optional project id to limit results to.
   *
   * @return \Drupal\node\Entity\Node[]
   *   Matched apartments.
   */
  private function filterApartments(array $params, ?int $projectId): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()->accessCheck(TRUE);
    $query->condition('type', 'apartment');
    $query->condition('status', 1);

    $apartmentUuids = $this->normalizeArrayParam($params['uuid'] ?? NULL, TRUE);
    if ($apartmentUuids) {
      $query->condition('uuid', $apartmentUuids, 'IN');
    }

    $restrictApartmentIds = NULL;
    $projectUuids = $this->normalizeArrayParam(
      $this->getParam($params, 'project_uuid'),
      TRUE
    );
    if ($projectUuids) {
      $projects = $this->loadProjectsByUuid($projectUuids);
      if (!$projects) {
        return [];
      }
      $restrictApartmentIds = $this->getApartmentIdsForProjects(...$projects);
      if (!$restrictApartmentIds) {
        return [];
      }
    }

    if ($projectId !== NULL) {
      $project = $this->loadProject($projectId);
      if (!$project) {
        return [];
      }
      $apartmentIds = $this->getApartmentIdsForProjects($project);
      if (!$apartmentIds) {
        return [];
      }
      if ($restrictApartmentIds !== NULL) {
        $apartmentIds = array_values(array_intersect($apartmentIds, $restrictApartmentIds));
        if (!$apartmentIds) {
          return [];
        }
      }
      $restrictApartmentIds = $apartmentIds;
    }

    if ($restrictApartmentIds !== NULL) {
      $query->condition('nid', $restrictApartmentIds, 'IN');
    }

    $apartmentIds = $query->execute();
    if (!$apartmentIds) {
      return [];
    }

    $apartments = $storage->loadMultiple($apartmentIds);
    $matches = [];

    $projectOwnershipTypes = $this->normalizeArrayParam($params['project_ownership_type'] ?? NULL, TRUE);
    $projectDistricts = $this->normalizeArrayParam($params['project_district'] ?? NULL, TRUE);
    $projectBuildingTypes = $this->normalizeArrayParam($params['project_building_type'] ?? NULL, TRUE);
    $projectNewDevStatus = $this->normalizeArrayParam(
      $params['project_new_development_status'] ?? $params['new_development_status'] ?? NULL,
      TRUE
    );
    $projectStatesOfSale = $this->normalizeArrayParam($params['project_state_of_sale'] ?? NULL, TRUE);
    $roomCounts = $this->normalizeArrayParam($params['room_count'] ?? NULL, FALSE);
    $livingArea = $this->normalizeArrayParam($params['living_area'] ?? NULL, FALSE);
    $properties = $this->normalizeArrayParam($params['properties'] ?? NULL, TRUE);

    $price = isset($params['price']) ? (int) $params['price'] : NULL;
    $debtFreeSalesPrice = isset($params['debt_free_sales_price']) ? (int) $params['debt_free_sales_price'] : NULL;

    $projectHasElevator = $this->normalizeBooleanParam($params['project_has_elevator'] ?? NULL);
    $projectHasSauna = $this->normalizeBooleanParam($params['project_has_sauna'] ?? NULL);
    $hasApartmentSauna = $this->normalizeBooleanParam($params['has_apartment_sauna'] ?? NULL);
    $hasTerrace = $this->normalizeBooleanParam($params['has_terrace'] ?? NULL);
    $hasBalcony = $this->normalizeBooleanParam($params['has_balcony'] ?? NULL);
    $hasYard = $this->normalizeBooleanParam($params['has_yard'] ?? NULL);

    foreach ($apartments as $apartment) {
      if (!$apartment instanceof Node) {
        continue;
      }

      if ($apartment->get('field_apartment_state_of_sale')->isEmpty()) {
        continue;
      }
      $apartmentState = strtolower((string) $apartment->get('field_apartment_state_of_sale')->target_id);
      if ($apartmentState === 'sold') {
        continue;
      }

      $project = $this->getProjectForApartment($apartment);
      if (!$project || !$project->isPublished()) {
        continue;
      }
      if ($project->hasField('field_archived') && (bool) $project->get('field_archived')->value) {
        continue;
      }

      $projectState = $this->normalizeTermValue($project, 'field_state_of_sale');
      if ($projectStatesOfSale) {
        if (in_array('upcoming', $projectStatesOfSale, TRUE)) {
          if ($projectState !== 'upcoming') {
            continue;
          }
        }
        elseif ($projectState === 'upcoming') {
          continue;
        }
      }
      elseif ($projectState === 'upcoming') {
        continue;
      }

      if ($projectOwnershipTypes) {
        $ownership = $this->normalizeTermValue($project, 'field_ownership_type');
        if (!in_array($ownership, $projectOwnershipTypes, TRUE)) {
          continue;
        }
      }

      if ($projectDistricts) {
        $district = $this->normalizeTermLabel($project, 'field_district');
        if (!in_array($district, $projectDistricts, TRUE)) {
          continue;
        }
      }

      if ($projectBuildingTypes) {
        $buildingType = $this->normalizeComputedValue($apartment, 'asu_project_building_type', TRUE);
        if (!in_array($buildingType, $projectBuildingTypes, TRUE)) {
          continue;
        }
      }

      if ($projectNewDevStatus) {
        $newDevStatus = $this->normalizeComputedValue($apartment, 'asu_new_development_status', TRUE);
        if (!in_array($newDevStatus, $projectNewDevStatus, TRUE)) {
          continue;
        }
      }

      if ($projectHasElevator !== NULL) {
        if ((bool) $project->get('field_has_elevator')->value !== $projectHasElevator) {
          continue;
        }
      }
      if ($projectHasSauna !== NULL) {
        if ((bool) $project->get('field_has_sauna')->value !== $projectHasSauna) {
          continue;
        }
      }
      if ($hasApartmentSauna !== NULL) {
        if ((bool) $apartment->get('field_has_apartment_sauna')->value !== $hasApartmentSauna) {
          continue;
        }
      }
      if ($hasTerrace !== NULL) {
        if ((bool) $apartment->get('field_has_terrace')->value !== $hasTerrace) {
          continue;
        }
      }
      if ($hasBalcony !== NULL) {
        if ((bool) $apartment->get('field_has_balcony')->value !== $hasBalcony) {
          continue;
        }
      }
      if ($hasYard !== NULL) {
        if ((bool) $apartment->get('field_has_yard')->value !== $hasYard) {
          continue;
        }
      }

      if ($properties) {
        $propertyMatches = TRUE;
        foreach ($properties as $property) {
          $propertyMatches = $propertyMatches && $this->matchesProperty($property, $project, $apartment);
        }
        if (!$propertyMatches) {
          continue;
        }
      }

      if ($roomCounts) {
        $roomCount = (int) filter_var((string) $apartment->get('field_apartment_structure')->value, FILTER_SANITIZE_NUMBER_INT);
        $roomCountsNormalized = array_map('intval', $roomCounts);
        if (in_array(5, $roomCountsNormalized, TRUE)) {
          $roomCountsNormalized = array_filter($roomCountsNormalized, static fn ($value) => $value !== 5);
          if ($roomCount < 5 && !in_array($roomCount, $roomCountsNormalized, TRUE)) {
            continue;
          }
        }
        elseif (!in_array($roomCount, $roomCountsNormalized, TRUE)) {
          continue;
        }
      }

      if ($livingArea) {
        $min = $livingArea[0] !== '' ? (float) $livingArea[0] : NULL;
        $max = $livingArea[1] ?? '';
        $max = $max !== '' ? (float) $max : NULL;
        $area = (float) $apartment->get('field_living_area')->value;
        if ($min !== NULL && $area < $min) {
          continue;
        }
        if ($max !== NULL && $area > $max) {
          continue;
        }
      }

      if ($debtFreeSalesPrice !== NULL && $price === NULL) {
        $apartmentDebtFree = (int) ((float) $apartment->get('field_debt_free_sales_price')->value * 100);
        if ($apartmentDebtFree >= $debtFreeSalesPrice) {
          continue;
        }
      }

      if ($price !== NULL) {
        $ownership = $this->normalizeTermValue($project, 'field_ownership_type');
        $useDebtFree = $ownership === 'hitas';
        $fieldName = $useDebtFree ? 'field_debt_free_sales_price' : 'field_right_of_occupancy_payment';
        $apartmentPrice = (int) ((float) $apartment->get($fieldName)->value * 100);
        if ($apartmentPrice >= $price) {
          continue;
        }
      }

      $matches[] = $apartment;
    }

    return $matches;
  }

  /**
   * Filter projects directly when no apartment filters are used.
   *
   * @param array $params
   *   Query parameters.
   *
   * @return \Drupal\node\Entity\Node[]
   *   Matched projects.
   */
  private function filterProjects(array $params): array {
    $projectUuids = $this->normalizeArrayParam(
      $this->getParam($params, 'project_uuid'),
      TRUE
    );
    if ($projectUuids) {
      $projects = $this->loadProjectsByUuid($projectUuids);
    }
    else {
      $storage = $this->entityTypeManager->getStorage('node');
      $projectIds = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'project')
        ->condition('status', 1)
        ->execute();
      $projects = $projectIds ? $storage->loadMultiple($projectIds) : [];
    }

    $projects = array_values(array_filter(
      $projects,
      static fn ($project) => $project instanceof Node
    ));

    $projectOwnershipTypes = $this->normalizeArrayParam(
      $params['project_ownership_type'] ?? NULL,
      TRUE
    );
    $projectDistricts = $this->normalizeArrayParam($params['project_district'] ?? NULL, TRUE);
    $projectBuildingTypes = $this->normalizeArrayParam(
      $params['project_building_type'] ?? NULL,
      TRUE
    );
    $projectNewDevStatus = $this->normalizeArrayParam(
      $params['project_new_development_status'] ?? $params['new_development_status'] ?? NULL,
      TRUE
    );
    $projectStatesOfSale = $this->normalizeArrayParam(
      $params['project_state_of_sale'] ?? NULL,
      TRUE
    );
    $projectHasElevator = $this->normalizeBooleanParam(
      $params['project_has_elevator'] ?? NULL
    );
    $projectHasSauna = $this->normalizeBooleanParam($params['project_has_sauna'] ?? NULL);

    $matches = [];
    foreach ($projects as $project) {
      if (!$project instanceof Node) {
        continue;
      }
      if (!$project->isPublished()) {
        continue;
      }
      if ($project->hasField('field_archived') && (bool) $project->get('field_archived')->value) {
        continue;
      }

      $projectState = $this->normalizeTermValue($project, 'field_state_of_sale');
      if ($projectStatesOfSale) {
        if (in_array('upcoming', $projectStatesOfSale, TRUE)) {
          if ($projectState !== 'upcoming') {
            continue;
          }
        }
        elseif ($projectState === 'upcoming') {
          continue;
        }
      }
      elseif ($projectState === 'upcoming') {
        continue;
      }

      if ($projectOwnershipTypes) {
        $ownership = $this->normalizeTermValue($project, 'field_ownership_type');
        if (!in_array($ownership, $projectOwnershipTypes, TRUE)) {
          continue;
        }
      }

      if ($projectDistricts) {
        $district = $this->normalizeTermLabel($project, 'field_district');
        if (!in_array($district, $projectDistricts, TRUE)) {
          continue;
        }
      }

      if ($projectBuildingTypes) {
        $buildingType = $this->normalizeTermValue($project, 'field_building_type');
        if (!in_array($buildingType, $projectBuildingTypes, TRUE)) {
          continue;
        }
      }

      if ($projectNewDevStatus) {
        $newDevStatus = $this->normalizeTermValue($project, 'field_new_development_status');
        if (!in_array($newDevStatus, $projectNewDevStatus, TRUE)) {
          continue;
        }
      }

      if ($projectHasElevator !== NULL) {
        if ((bool) $project->get('field_has_elevator')->value !== $projectHasElevator) {
          continue;
        }
      }
      if ($projectHasSauna !== NULL) {
        if ((bool) $project->get('field_has_sauna')->value !== $projectHasSauna) {
          continue;
        }
      }

      $matches[] = $project;
    }

    return $matches;
  }

  /**
   * Check whether any apartment-specific filters are present.
   */
  private function hasApartmentFilters(array $params): bool {
    $apartmentParams = [
      'room_count',
      'living_area',
      'price',
      'debt_free_sales_price',
      'has_apartment_sauna',
      'has_terrace',
      'has_balcony',
      'has_yard',
    ];
    foreach ($apartmentParams as $param) {
      if (isset($params[$param]) && $params[$param] !== '' && $params[$param] !== []) {
        return TRUE;
      }
    }

    if (!empty($params['properties']) && is_array($params['properties'])) {
      $apartmentProperties = [
        'has_apartment_sauna',
        'has_terrace',
        'has_balcony',
        'has_yard',
      ];
      $properties = array_map('strtolower', $params['properties']);
      foreach ($apartmentProperties as $property) {
        if (in_array($property, $properties, TRUE)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Resolve the project for an apartment.
   */
  private function getProjectForApartment(Node $apartment): ?Node {
    $storage = $this->entityTypeManager->getStorage('node');
    $projects = $storage->loadByProperties([
      'type' => 'project',
      'field_apartments' => $apartment->id(),
    ]);

    $project = reset($projects);
    return $project instanceof Node ? $project : NULL;
  }

  /**
   * Load projects by UUIDs.
   *
   * @param string[] $uuids
   *   Project UUIDs.
   *
   * @return \Drupal\node\Entity\Node[]
   *   Projects.
   */
  private function loadProjectsByUuid(array $uuids): array {
    $uuids = array_values(array_filter($uuids, static fn ($uuid) => $uuid !== ''));
    if (!$uuids) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $projectIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'project')
      ->condition('uuid', $uuids, 'IN')
      ->execute();
    if (!$projectIds) {
      return [];
    }
    $projects = $storage->loadMultiple($projectIds);
    return array_values(array_filter(
      $projects,
      static fn ($project) => $project instanceof FieldableEntityInterface
    ));
  }

  /**
   * Collect apartment IDs for projects.
   *
   * @return int[]
   *   Apartment node IDs.
   */
  private function getApartmentIdsForProjects(FieldableEntityInterface ...$projects): array {
    $apartmentIds = [];
    foreach ($projects as $project) {
      if (!$project->hasField('field_apartments') || $project->get('field_apartments')->isEmpty()) {
        continue;
      }
      foreach ($project->get('field_apartments')->getValue() as $item) {
        if (isset($item['target_id'])) {
          $apartmentIds[] = (int) $item['target_id'];
        }
      }
    }
    $apartmentIds = array_values(array_unique($apartmentIds));
    return array_values(array_filter($apartmentIds, static fn ($id) => $id > 0));
  }

  /**
   * Normalize array-like query parameters.
   */
  private function normalizeArrayParam(mixed $value, bool $lowercase): array {
    if ($value === NULL || $value === '') {
      return [];
    }
    if (is_string($value)) {
      $value = array_map('trim', explode(',', $value));
    }
    if (!is_array($value)) {
      return [];
    }
    $value = array_values(array_filter($value, static fn ($item) => $item !== '' && $item !== NULL));
    if ($lowercase) {
      $value = array_map(static fn ($item) => strtolower((string) $item), $value);
    }
    return $value;
  }

  /**
   * Get a request param, supporting hyphen/underscore variants.
   */
  private function getParam(array $params, string $key): mixed {
    if (array_key_exists($key, $params)) {
      return $params[$key];
    }
    $hyphenKey = str_replace('_', '-', $key);
    return $params[$hyphenKey] ?? NULL;
  }

  /**
   * Normalize boolean query params.
   */
  private function normalizeBooleanParam(mixed $value): ?bool {
    if ($value === NULL || $value === '') {
      return NULL;
    }
    return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
  }

  /**
   * Normalize a term machine name or label to lowercase.
   */
  private function normalizeTermValue(Node $entity, string $fieldName): string {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return '';
    }
    $targetId = $entity->get($fieldName)->target_id;
    if (!$targetId) {
      return '';
    }
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($targetId);
    if (!$term instanceof FieldableEntityInterface) {
      return '';
    }
    $value = $term->hasField('field_machine_readable_name') && !$term->get('field_machine_readable_name')->isEmpty()
      ? $term->get('field_machine_readable_name')->value
      : $term->label();
    return strtolower((string) $value);
  }

  /**
   * Normalize a term label.
   */
  private function normalizeTermLabel(Node $entity, string $fieldName): string {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return '';
    }
    $targetId = $entity->get($fieldName)->target_id;
    if (!$targetId) {
      return '';
    }
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($targetId);
    return $term instanceof FieldableEntityInterface ? strtolower($term->label()) : '';
  }

  /**
   * Normalize computed field markup value.
   */
  private function normalizeComputedValue(Node $entity, string $fieldName, bool $lowercase): string {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return '';
    }
    $value = $entity->get($fieldName)->value ?? $entity->get($fieldName)->getValue()[0]['#markup'] ?? '';
    $value = (string) $value;
    return $lowercase ? strtolower($value) : $value;
  }

  /**
   * Match boolean property filters against project/apartment fields.
   */
  private function matchesProperty(string $property, Node $project, Node $apartment): bool {
    $propertyMap = [
      'project_has_elevator' => [$project, 'field_has_elevator'],
      'project_has_sauna' => [$project, 'field_has_sauna'],
      'has_apartment_sauna' => [$apartment, 'field_has_apartment_sauna'],
      'has_terrace' => [$apartment, 'field_has_terrace'],
      'has_balcony' => [$apartment, 'field_has_balcony'],
      'has_yard' => [$apartment, 'field_has_yard'],
    ];

    if (!isset($propertyMap[$property])) {
      return FALSE;
    }

    [$entity, $fieldName] = $propertyMap[$property];
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return FALSE;
    }

    return (bool) $entity->get($fieldName)->value;
  }

}
