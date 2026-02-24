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
   * @param bool $returnAll
   *   If TRUE, return all apartments without filtering (bypasses params).
   *
   * @return array{total:int,items:\Drupal\node\Entity\Node[]}
   *   Filtered and paginated results.
   */
  public function searchApartments(
    array $params,
    ?int $projectId,
    int $offset,
    int $limit,
    bool $returnAll = FALSE,
  ): array {

    if ($returnAll) {
      return $this->loadAllApartmentsPage($offset, $limit);
    }

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
   * Load a single apartment by node ID or UUID.
   *
   * @param int|string $idOrUuid
   *   Node ID (nid) or apartment UUID.
   *
   * @return \Drupal\node\Entity\Node|null
   *   The apartment node, or NULL if not found.
   */
  public function loadApartment(int|string $idOrUuid): ?Node {
    if ($this->isUuid((string) $idOrUuid)) {
      return $this->loadApartmentByUuid((string) $idOrUuid);
    }
    $apartment = $this->entityTypeManager->getStorage('node')->load((int) $idOrUuid);
    if ($apartment instanceof Node && $apartment->bundle() === 'apartment') {
      return $apartment;
    }
    return NULL;
  }

  /**
   * Load a single apartment by UUID.
   */
  public function loadApartmentByUuid(string $uuid): ?Node {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'apartment',
      'uuid' => $uuid,
    ]);
    $apartment = $nodes ? reset($nodes) : NULL;
    return $apartment instanceof Node ? $apartment : NULL;
  }

  /**
   * Check if a string matches UUID format.
   */
  private function isUuid(string $value): bool {
    return (bool) preg_match(
      '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
      $value
    );
  }

  /**
   * Load a single project by node ID or UUID.
   *
   * @param int|string $idOrUuid
   *   Node ID (nid) or project UUID.
   *
   * @return \Drupal\node\Entity\Node|null
   *   The project node, or NULL if not found.
   */
  public function loadProject(int|string $idOrUuid): ?Node {
    if ($this->isUuid((string) $idOrUuid)) {
      return $this->loadProjectByUuid((string) $idOrUuid);
    }
    $project = $this->entityTypeManager->getStorage('node')->load((int) $idOrUuid);
    if ($project instanceof Node && $project->bundle() === 'project') {
      return $project;
    }
    return NULL;
  }

  /**
   * Load a single project by UUID.
   */
  public function loadProjectByUuid(string $uuid): ?Node {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'project',
      'uuid' => $uuid,
    ]);
    $project = $nodes ? reset($nodes) : NULL;
    return $project instanceof Node ? $project : NULL;
  }

  /**
   * Load one page of apartments without node-access filtering.
   *
   * @return array{total:int,items:\Drupal\node\Entity\Node[]}
   *   Total count and current page items.
   */
  private function loadAllApartmentsPage(int $offset, int $limit): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $baseQuery = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'apartment');

    $total = (int) (clone $baseQuery)
      ->count()
      ->execute();

    if ($total === 0) {
      return [
        'total' => 0,
        'items' => [],
      ];
    }

    $apartmentIds = (clone $baseQuery)
      ->sort('nid', 'ASC')
      ->range($offset, $limit)
      ->execute();

    if (!$apartmentIds) {
      return [
        'total' => $total,
        'items' => [],
      ];
    }

    $apartments = $storage->loadMultiple($apartmentIds);
    $items = array_values(array_filter(
      $apartments,
      static fn ($apartment) => $apartment instanceof Node
    ));

    return [
      'total' => $total,
      'items' => $items,
    ];
  }

  /**
   * Filter apartments and return the matched nodes.
   *
   * Project-level filters are applied via a project query; apartment-level
   * filters are applied in the apartment entity query. The price filter
   * (which depends on project ownership) and room_count (parsed from
   * structure string) may require a final PHP pass when applicable.
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

    $hasExplicitProjectRestriction = $restrictApartmentIds !== NULL;
    if (!$hasExplicitProjectRestriction) {
      $paramsForProjectFilter = $this->mergePropertiesIntoParams($params);
      $projectIdsFromFilter = $this->getProjectIdsMatchingApartmentFilters($paramsForProjectFilter);
      $restrictApartmentIds = $this->getApartmentIdsForProjectIds($projectIdsFromFilter);
      if (!$restrictApartmentIds) {
        return [];
      }
    }

    $query = $storage->getQuery()->accessCheck(TRUE);
    $query->condition('type', 'apartment');

    $apartmentUuids = $this->normalizeArrayParam($params['uuid'] ?? NULL, TRUE);
    if ($apartmentUuids) {
      $query->condition('uuid', $apartmentUuids, 'IN');
    }

    if ($restrictApartmentIds !== NULL) {
      $query->condition('nid', $restrictApartmentIds, 'IN');
    }

    $query->condition('field_apartment_state_of_sale', 'sold', '<>');
    $query->exists('field_apartment_state_of_sale');

    $projectOwnershipTypes = $this->normalizeArrayParam($params['project_ownership_type'] ?? NULL, TRUE);
    $propertyOptions = $this->resolvePropertyOptions($params);
    $this->addApartmentPropertyConditions($query, $propertyOptions);

    $livingArea = $this->normalizeArrayParam($params['living_area'] ?? NULL, FALSE);
    if ($livingArea) {
      $min = $livingArea[0] !== '' ? (float) $livingArea[0] : NULL;
      $max = $livingArea[1] ?? '';
      $max = $max !== '' ? (float) $max : NULL;
      if ($min !== NULL) {
        $query->condition('field_living_area', (string) $min, '>=');
      }
      if ($max !== NULL) {
        $query->condition('field_living_area', (string) $max, '<=');
      }
    }

    $debtFreeSalesPrice = isset($params['debt_free_sales_price']) ? (int) $params['debt_free_sales_price'] : NULL;
    $price = isset($params['price']) ? (int) $params['price'] : NULL;

    $this->addApartmentPriceConditions($query, $params, $price, $debtFreeSalesPrice);

    $apartmentIds = $query->execute();
    if (!$apartmentIds) {
      return [];
    }

    $apartments = $storage->loadMultiple($apartmentIds);

    $roomCounts = $this->normalizeArrayParam($params['room_count'] ?? NULL, FALSE);
    $matches = [];
    foreach ($apartments as $apartment) {
      if (!$apartment instanceof Node) {
        continue;
      }
      if ($roomCounts) {
        $roomCount = (int) filter_var(
          (string) ($apartment->get('field_apartment_structure')->value ?? ''),
          FILTER_SANITIZE_NUMBER_INT
        );
        $roomCountsNormalized = array_map('intval', $roomCounts);
        if (in_array(5, $roomCountsNormalized, TRUE)) {
          $roomCountsNormalized = array_filter($roomCountsNormalized, static fn ($v) => $v !== 5);
          if ($roomCount < 5 && !in_array($roomCount, $roomCountsNormalized, TRUE)) {
            continue;
          }
        }
        elseif (!in_array($roomCount, $roomCountsNormalized, TRUE)) {
          continue;
        }
      }
      if ($price !== NULL && !$projectOwnershipTypes) {
        $project = $this->getProjectForApartment($apartment);
        if (!$project) {
          continue;
        }
        $ownership = $this->normalizeTermValue($project, 'field_ownership_type');
        $useDebtFree = $ownership === 'hitas';
        $fieldName = $useDebtFree ? 'field_debt_free_sales_price' : 'field_right_of_occupancy_payment';
        $apartmentPrice = (int) ((float) ($apartment->get($fieldName)->value ?? 0) * 100);
        if ($apartmentPrice >= $price) {
          continue;
        }
      }
      $matches[] = $apartment;
    }

    return array_values($matches);
  }

  /**
   * Get project IDs matching all project-level filters for apartment search.
   *
   * Applies archived=0 and state-of-sale (exclude upcoming by default).
   * Returns IDs to restrict apartments to matching projects.
   *
   * @return int[]
   *   Project IDs.
   */
  private function getProjectIdsMatchingApartmentFilters(array $params): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()->accessCheck(TRUE);
    $query->condition('type', 'project');
    $query->condition('field_archived', 0);
    $query->exists('field_apartments');

    $projectStatesOfSale = $this->normalizeArrayParam($params['project_state_of_sale'] ?? NULL, TRUE);
    $this->addProjectStateCondition($query, $projectStatesOfSale);

    $projectOwnershipTypes = $this->normalizeArrayParam($params['project_ownership_type'] ?? NULL, TRUE);
    if ($projectOwnershipTypes) {
      $query->condition('field_ownership_type.entity.name', $projectOwnershipTypes, 'IN');
    }
    $projectDistricts = $this->normalizeArrayParam($params['project_district'] ?? NULL, TRUE);
    if ($projectDistricts) {
      $query->condition('field_district.entity.name', $projectDistricts, 'IN');
    }
    $projectBuildingTypes = $this->normalizeArrayParam($params['project_building_type'] ?? NULL, TRUE);
    if ($projectBuildingTypes) {
      $query->condition('field_building_type.entity.name', $projectBuildingTypes, 'IN');
    }
    $projectNewDevStatus = $this->normalizeArrayParam(
      $params['project_new_development_status'] ?? $params['new_development_status'] ?? NULL,
      TRUE
    );
    if ($projectNewDevStatus) {
      $query->condition('field_new_development_status.entity.name', $projectNewDevStatus, 'IN');
    }
    $projectHasElevator = $this->normalizeBooleanParam($params['project_has_elevator'] ?? NULL);
    if ($projectHasElevator !== NULL) {
      $query->condition('field_has_elevator', (int) $projectHasElevator);
    }
    $projectHasSauna = $this->normalizeBooleanParam($params['project_has_sauna'] ?? NULL);
    if ($projectHasSauna !== NULL) {
      $query->condition('field_has_sauna', (int) $projectHasSauna);
    }

    $ids = $query->execute();
    return $ids ? array_values(array_map('intval', $ids)) : [];
  }

  /**
   * Merge properties array into params for project filter.
   *
   * When properties includes project_has_elevator or project_has_sauna,
   * they imply filter value true.
   */
  private function mergePropertiesIntoParams(array $params): array {
    $properties = $this->normalizeArrayParam($params['properties'] ?? NULL, TRUE);
    if (!$properties) {
      return $params;
    }
    $merged = $params;
    if (in_array('project_has_elevator', $properties, TRUE)
      && !isset($merged['project_has_elevator'])) {
      $merged['project_has_elevator'] = TRUE;
    }
    if (in_array('project_has_sauna', $properties, TRUE)
      && !isset($merged['project_has_sauna'])) {
      $merged['project_has_sauna'] = TRUE;
    }
    return $merged;
  }

  /**
   * Resolve property filter options from params and properties array.
   */
  private function resolvePropertyOptions(array $params): array {
    $properties = $this->normalizeArrayParam($params['properties'] ?? NULL, TRUE);
    $resolve = function (string $key) use ($params, $properties): ?bool {
      $direct = $this->normalizeBooleanParam($params[$key] ?? NULL);
      if ($direct !== NULL) {
        return $direct;
      }
      return in_array($key, $properties, TRUE) ? TRUE : NULL;
    };
    return [
      'has_apartment_sauna' => $resolve('has_apartment_sauna'),
      'has_terrace' => $resolve('has_terrace'),
      'has_balcony' => $resolve('has_balcony'),
      'has_yard' => $resolve('has_yard'),
    ];
  }

  /**
   * Add apartment property conditions to the query.
   *
   * Project-level properties (elevator, sauna) are applied via
   * getProjectIdsMatchingApartmentFilters in the main flow.
   */
  private function addApartmentPropertyConditions($query, array $options): void {
    if ($options['has_apartment_sauna'] !== NULL) {
      $query->condition('field_has_apartment_sauna', (int) $options['has_apartment_sauna']);
    }
    if ($options['has_terrace'] !== NULL) {
      $query->condition('field_has_terrace', (int) $options['has_terrace']);
    }
    if ($options['has_balcony'] !== NULL) {
      $query->condition('field_has_balcony', (int) $options['has_balcony']);
    }
    if ($options['has_yard'] !== NULL) {
      $query->condition('field_has_yard', (int) $options['has_yard']);
    }
  }

  /**
   * Add price-related conditions to the apartment query.
   *
   * When project_ownership_type is set, we know which price field to use.
   * Otherwise debt_free_sales_price filter applies to all; price filter
   * requires PHP pass when ownership varies.
   */
  private function addApartmentPriceConditions($query, array $params, ?int $price, ?int $debtFreeSalesPrice): void {
    $ownershipTypes = $this->normalizeArrayParam($params['project_ownership_type'] ?? NULL, TRUE);
    $onlyHitas = $ownershipTypes === ['hitas'];
    $onlyNonHitas = $ownershipTypes && !in_array('hitas', $ownershipTypes, TRUE);
    if ($debtFreeSalesPrice !== NULL && $price === NULL) {
      $maxEuros = $debtFreeSalesPrice / 100.0;
      $query->condition('field_debt_free_sales_price', $maxEuros, '<');
    }
    if ($price !== NULL && $onlyHitas) {
      $maxEuros = $price / 100.0;
      $query->condition('field_debt_free_sales_price', $maxEuros, '<');
    }
    elseif ($price !== NULL && $onlyNonHitas) {
      $maxEuros = $price / 100.0;
      $query->condition('field_right_of_occupancy_payment', $maxEuros, '<');
    }
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
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'project');

    $projectUuids = $this->normalizeArrayParam(
      $this->getParam($params, 'project_uuid'),
      TRUE
    );
    if ($projectUuids) {
      $query->condition('uuid', $projectUuids, 'IN');
    }

    $query->condition('field_archived', 0);

    $projectStatesOfSale = $this->normalizeArrayParam(
      $this->getParam($params, 'project_state_of_sale'),
      TRUE
    );
    $this->addProjectStateCondition($query, $projectStatesOfSale);

    $projectOwnershipTypes = $this->normalizeArrayParam(
      $this->getParam($params, 'project_ownership_type'),
      TRUE
    );
    if ($projectOwnershipTypes) {
      $query->condition('field_ownership_type.entity.name', $projectOwnershipTypes, 'IN');
    }

    $projectDistricts = $this->normalizeArrayParam(
      $this->getParam($params, 'project_district'),
      TRUE
    );
    if ($projectDistricts) {
      $query->condition('field_district.entity.name', $projectDistricts, 'IN');
    }

    $projectBuildingTypes = $this->normalizeArrayParam(
      $this->getParam($params, 'project_building_type'),
      TRUE
    );
    if ($projectBuildingTypes) {
      $query->condition('field_building_type.entity.name', $projectBuildingTypes, 'IN');
    }

    $projectNewDevStatus = $this->normalizeArrayParam(
      $this->getParam($params, 'project_new_development_status')
        ?? $params['new_development_status'] ?? NULL,
      TRUE
    );
    if ($projectNewDevStatus) {
      $query->condition('field_new_development_status.entity.name', $projectNewDevStatus, 'IN');
    }

    $projectHasElevator = $this->normalizeBooleanParam(
      $this->getParam($params, 'project_has_elevator')
    );
    if ($projectHasElevator !== NULL) {
      $query->condition('field_has_elevator', (int) $projectHasElevator);
    }

    $projectHasSauna = $this->normalizeBooleanParam(
      $this->getParam($params, 'project_has_sauna')
    );
    if ($projectHasSauna !== NULL) {
      $query->condition('field_has_sauna', (int) $projectHasSauna);
    }

    $projectIds = $query->execute();
    if (!$projectIds) {
      return [];
    }

    $projects = $storage->loadMultiple($projectIds);
    return array_values(array_filter(
      $projects,
      static fn ($project) => $project instanceof Node
    ));
  }

  /**
   * Add project state of sale condition to the query.
   *
   * Uses field_state_of_sale (config term target_id) directly because Config
   * terms are config entities and do not support entity-query traversal into
   * their fields (e.g. .entity.field_machine_readable_name).
   *
   * Excludes upcoming by default; when filter is set, includes only requested
   * states.
   */
  private function addProjectStateCondition($query, array $projectStatesOfSale): void {
    $stateColumn = 'field_state_of_sale';
    if (empty($projectStatesOfSale)) {
      $query->condition($stateColumn, 'upcoming', '<>');
      return;
    }

    $query->condition($stateColumn, $projectStatesOfSale, 'IN');
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
    $projectIds = array_map(
      static fn ($p) => (int) $p->id(),
      array_filter($projects, static fn ($p) => $p->hasField('field_apartments') && !$p->get('field_apartments')->isEmpty())
    );
    if (!$projectIds) {
      return [];
    }
    return $this->getApartmentIdsForProjectIds($projectIds);
  }

  /**
   * Get apartment IDs that belong to the given project IDs.
   *
   * @param int[] $projectIds
   *   Project node IDs.
   *
   * @return int[]
   *   Apartment node IDs.
   */
  private function getApartmentIdsForProjectIds(array $projectIds): array {
    $projectIds = array_values(array_filter(array_map('intval', $projectIds), static fn ($id) => $id > 0));
    if (!$projectIds) {
      return [];
    }
    $connection = \Drupal::database();
    $query = $connection->select('node__field_apartments', 'f')
      ->fields('f', ['field_apartments_target_id'])
      ->condition('f.entity_id', $projectIds, 'IN');
    $result = $query->execute()->fetchCol();
    return array_values(array_filter(array_map('intval', $result), static fn ($id) => $id > 0));
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
