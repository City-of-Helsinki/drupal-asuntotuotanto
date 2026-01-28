<?php

declare(strict_types=1);

namespace Drupal\asu_rest\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Maps nodes to Elasticsearch-like _source payloads.
 */
final class SearchMapper {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly RequestStack $requestStack,
  ) {
  }

  /**
   * Map an apartment for list responses.
   */
  public function mapApartmentListing(Node $apartment): array {
    $project = $this->getProjectForApartment($apartment);

    $data = [
      '_language' => $apartment->language()->getId(),
      'apartment_address' => $this->getComputedMarkup($apartment, 'field_apartment_address'),
      'apartment_number' => $this->getScalar($apartment, 'field_apartment_number'),
      'apartment_state_of_sale' => $this->getEnumFromTermField($apartment, 'field_apartment_state_of_sale'),
      'apartment_structure' => $this->getScalar($apartment, 'field_apartment_structure'),
      'application_url' => $this->getComputedMarkup($apartment, 'asu_application_form_url'),
      'debt_free_sales_price' => $this->toCents($this->getScalar($apartment, 'field_debt_free_sales_price')),
      'floor' => $this->getScalar($apartment, 'field_floor'),
      'floor_max' => $this->getScalar($apartment, 'field_floor_max'),
      'housing_company_fee' => $this->toCents($this->getComputedMarkup($apartment, 'field_housing_company_fee')),
      'living_area' => $this->getScalar($apartment, 'field_living_area'),
      'nid' => $apartment->id(),
      'release_payment' => $this->toCents($this->getScalar($apartment, 'field_release_payment')),
      'right_of_occupancy_payment' => $this->toCents($this->getScalar($apartment, 'field_right_of_occupancy_payment')),
      'room_count' => $this->toNumber($this->getScalar($apartment, 'field_apartment_structure')),
      'sales_price' => $this->toCents($this->getScalar($apartment, 'field_sales_price')),
      'title' => $apartment->label(),
      'url' => $this->nodeUrl($apartment),
      'uuid' => $apartment->uuid(),
      'image_urls' => $this->getComputedList($apartment, 'asu_computed_apartment_images'),
      'services' => $this->getComputedList($apartment, 'multiple_values_field'),
    ];

    if ($project) {
      $data += $this->mapProjectFields($project, $apartment);
    }

    return $data;
  }

  /**
   * Map an apartment for detail responses.
   */
  public function mapApartmentDetail(Node $apartment): array {
    $data = $this->mapApartmentListing($apartment);

    $data += [
      'additional_information' => $this->getScalar($apartment, 'field_additional_information'),
      'balcony_description' => $this->getScalar($apartment, 'field_balcony_description'),
      'bathroom_appliances' => $this->getScalar($apartment, 'field_bathroom_appliances'),
      'condition' => $this->getTermLabel($apartment, 'field_condition'),
      'field_alteration_work' => $this->toCents($this->getScalar($apartment, 'field_alteration_work')),
      'field_index_adjusted_right_of_oc' => $this->toCents($this->getScalar($apartment, 'field_index_adjusted_right_of_oc')),
      'financing_fee' => $this->toCents($this->getScalar($apartment, 'field_financing_fee')),
      'floor_plan_image' => $this->getFileUrlFromField($apartment, 'field_floorplan'),
      'kitchen_appliances' => $this->getScalar($apartment, 'field_kitchen_appliances'),
      'loan_share' => $this->toCents($this->getScalar($apartment, 'field_loan_share')),
      'maintenance_fee' => $this->toCents($this->getScalar($apartment, 'field_maintenance_fee')),
      'other_fees' => $this->getScalar($apartment, 'field_other_fees'),
      'parking_fee' => $this->toCents($this->getScalar($apartment, 'field_parking_fee')),
      'parking_fee_explanation' => $this->getScalar($apartment, 'field_parking_fee_explanation'),
      'price_m2' => $this->toCents($this->getScalar($apartment, 'field_price_m2')),
      'publish_on_etuovi' => $this->getBoolean($apartment, 'field_publish_on_etuovi'),
      'publish_on_oikotie' => $this->getBoolean($apartment, 'field_publish_on_oikotie'),
      'right_of_occupancy_deposit' => $this->toCents($this->getScalar($apartment, 'field_right_of_occupancy_deposit')),
      'right_of_occupancy_fee' => $this->toCents($this->getScalar($apartment, 'field_right_of_occupancy_fee')),
      'services' => $this->getComputedList($apartment, 'multiple_values_field'),
      'services_description' => $this->getScalar($apartment, 'field_services_description'),
      'showing_times' => $this->formatDateTime($this->getScalar($apartment, 'field_showing_time')),
      'stock_end_number' => $this->getScalar($apartment, 'field_stock_end_number'),
      'stock_start_number' => $this->getScalar($apartment, 'field_stock_start_number'),
      'storage_description' => $this->getScalar($apartment, 'field_storage_description'),
      'view_description' => $this->getScalar($apartment, 'field_view_description'),
      'water_fee' => $this->toCents($this->getScalar($apartment, 'field_water_fee')),
      'water_fee_explanation' => $this->getScalar($apartment, 'field_water_fee_explanation'),
    ];

    return $data;
  }

  /**
   * Map a project for list/detail responses.
   */
  public function mapProject(Node $project): array {
    return $this->mapProjectFields($project, NULL);
  }

  /**
   * Build a raw Elasticsearch-style response payload.
   *
   * @param array[] $sources
   *   The mapped _source data.
   * @param int $total
   *   Total hits before pagination.
   * @param string $indexName
   *   Index name to report in hits.
   *
   * @return array
   *   Elasticsearch-like response envelope.
   */
  public function buildSearchResponse(array $sources, int $total, string $indexName): array {
    $hits = [];

    foreach ($sources as $source) {
      $id = $source['nid'] ?? $source['project_id'] ?? $source['uuid'] ?? NULL;
      $hits[] = [
        '_index' => $indexName,
        '_id' => $id,
        '_score' => 1.0,
        '_source' => $source,
      ];
    }

    return [
      'took' => 0,
      'timed_out' => FALSE,
      '_shards' => [
        'total' => 1,
        'successful' => 1,
        'skipped' => 0,
        'failed' => 0,
      ],
      'hits' => [
        'total' => [
          'value' => $total,
          'relation' => 'eq',
        ],
        'max_score' => $hits ? 1.0 : NULL,
        'hits' => $hits,
      ],
    ];
  }

  /**
   * Map project fields shared with apartment listings.
   */
  private function mapProjectFields(Node $project, ?Node $apartment): array {
    $data = [
      'project_application_end_time' => $this->formatDateTime($this->getScalar($project, 'field_application_end_time')),
      'project_application_start_time' => $this->formatDateTime($this->getScalar($project, 'field_application_start_time')),
      'project_can_apply_afterwards' => $this->getBoolean($project, 'field_can_apply_afterwards'),
      'project_building_type' => $apartment ? $this->getComputedMarkup($apartment, 'asu_project_building_type') : $this->getEnumFromTermField($project, 'field_building_type'),
      'project_coordinate_lat' => $this->getScalar($project, 'field_coordinate_lat'),
      'project_coordinate_lon' => $this->getScalar($project, 'field_coordinate_lon'),
      'project_district' => $this->getTermLabel($project, 'field_district'),
      'project_estimated_completion' => $this->getScalar($project, 'field_estimated_completion'),
      'project_housing_company' => $this->getScalar($project, 'field_housing_company'),
      'project_id' => $project->id(),
      'project_image_urls' => $this->getFileUrlsFromField($project, 'field_images'),
      'project_main_image_url' => $this->getFileUrlFromField($project, 'field_main_image'),
      'project_construction_materials' => $this->getTermLabels($project, 'field_construction_materials'),
      'project_new_development_status' => $apartment ? $this->getComputedMarkup($apartment, 'asu_new_development_status') : $this->getEnumFromTermField($project, 'field_new_development_status'),
      'project_ownership_type' => $this->getLowercaseTermName($project, 'field_ownership_type'),
      'project_possession_transfer_date' => $this->formatDateTime($this->getScalar($project, 'field_possession_transfer_date')),
      'project_state_of_sale' => $this->getEnumFromTermField($project, 'field_state_of_sale'),
      'project_street_address' => $this->getScalar($project, 'field_street_address'),
      'project_upcoming_description' => $this->getScalar($project, 'field_upcoming_description'),
      'project_url' => $this->nodeUrl($project),
      'project_uuid' => $project->uuid(),
    ];

    $data += [
      'project_city' => $this->getScalar($project, 'field_city'),
      'project_description' => $this->getScalar($project, 'field_project_description'),
      'project_archived' => $this->getBoolean($project, 'field_archived'),
      'project_apartment_count' => $this->getScalar($project, 'field_apartment_count'),
      'project_heating_options' => $this->getTermLabels($project, 'field_heating_options'),
      'project_material_choice_dl' => $this->getScalar($project, 'field_material_choice_dl'),
      'project_premarketing_start_time' => $this->formatDateTime($this->getScalar($project, 'field_premarketing_start_time')),
      'project_premarketing_end_time' => $this->formatDateTime($this->getScalar($project, 'field_premarketing_end_time')),
    ];

    if ($apartment) {
      $data['project_construction_materials'] = $this->getTermLabels($project, 'field_construction_materials');
    }

    return $data;
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
   * Get scalar field value.
   */
  private function getScalar(Node $entity, string $fieldName): string {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return '';
    }
    return (string) $entity->get($fieldName)->value;
  }

  /**
   * Get boolean field value.
   */
  private function getBoolean(Node $entity, string $fieldName): bool {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return FALSE;
    }
    return (bool) $entity->get($fieldName)->value;
  }

  /**
   * Get term label from a field.
   */
  private function getTermLabel(Node $entity, string $fieldName): string {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return '';
    }
    $term = $this->entityTypeManager->getStorage('taxonomy_term')
      ->load($entity->get($fieldName)->target_id);
    return $term instanceof Term ? $term->label() : '';
  }

  /**
   * Get term labels from a multi-value field.
   */
  private function getTermLabels(Node $entity, string $fieldName): array {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return [];
    }
    $labels = [];
    foreach ($entity->get($fieldName)->getValue() as $item) {
      if (!isset($item['target_id'])) {
        continue;
      }
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($item['target_id']);
      if ($term instanceof Term) {
        $labels[] = $term->label();
      }
    }
    return $labels;
  }

  /**
   * Get enum value from term field.
   */
  private function getEnumFromTermField(Node $entity, string $fieldName): string {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return '';
    }
    $term = $this->entityTypeManager->getStorage('taxonomy_term')
      ->load($entity->get($fieldName)->target_id);
    if (!$term instanceof Term) {
      return '';
    }
    $value = $term->hasField('field_machine_readable_name') && !$term->get('field_machine_readable_name')->isEmpty()
      ? $term->get('field_machine_readable_name')->value
      : $term->label();

    return $this->normalizeEnum((string) $value);
  }

  /**
   * Get lowercase term name for ownership types.
   */
  private function getLowercaseTermName(Node $entity, string $fieldName): string {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return '';
    }
    $term = $this->entityTypeManager->getStorage('taxonomy_term')
      ->load($entity->get($fieldName)->target_id);
    if (!$term instanceof Term) {
      return '';
    }
    $value = $term->label();
    return strtolower((string) $value);
  }

  /**
   * Get computed field markup value.
   */
  private function getComputedMarkup(Node $entity, string $fieldName): string {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return '';
    }
    $value = $entity->get($fieldName)->value ?? $entity->get($fieldName)->getValue()[0]['#markup'] ?? '';
    return (string) $value;
  }

  /**
   * Get a list of computed field markup values.
   */
  private function getComputedList(Node $entity, string $fieldName): array {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return [];
    }
    $values = [];
    foreach ($entity->get($fieldName)->getValue() as $item) {
      if (isset($item['#markup'])) {
        $values[] = $item['#markup'];
      }
      elseif (isset($item['value'])) {
        $values[] = $item['value'];
      }
    }
    return $values;
  }

  /**
   * Convert numbers to cents.
   */
  private function toCents(string $value): int {
    if ($value === '') {
      return 0;
    }
    return (int) ((float) $value * 100);
  }

  /**
   * Extract number from a string.
   */
  private function toNumber(string $value): int {
    return $value !== '' ? (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT) : 0;
  }

  /**
   * Format date time values.
   */
  private function formatDateTime(string $value): string {
    if ($value === '') {
      return '';
    }
    if (function_exists('asu_content_convert_datetime')) {
      return asu_content_convert_datetime($value);
    }
    return $value;
  }

  /**
   * Convert enum values to uppercase with underscores.
   */
  private function normalizeEnum(string $value): string {
    if ($value === '') {
      return '';
    }
    $value = str_replace('apartment_for_sale', 'for_sale', $value);
    $value = strtoupper(str_replace([' ', '-'], '_', $value));
    return $value;
  }

  /**
   * Get file URLs from a multi-value image field.
   */
  private function getFileUrlsFromField(Node $entity, string $fieldName): array {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return [];
    }
    $urls = [];
    foreach ($entity->get($fieldName)->getValue() as $item) {
      if (!isset($item['target_id'])) {
        continue;
      }
      if ($url = $this->buildFileUrl((int) $item['target_id'])) {
        $urls[] = $url;
      }
    }
    return $urls;
  }

  /**
   * Get a single file URL from a field.
   */
  private function getFileUrlFromField(Node $entity, string $fieldName): ?string {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return NULL;
    }
    $targetId = $entity->get($fieldName)->target_id;
    return $targetId ? $this->buildFileUrl((int) $targetId) : NULL;
  }

  /**
   * Build URL for a file.
   */
  private function buildFileUrl(int $fileId): ?string {
    $file = File::load($fileId);
    if (!$file) {
      return NULL;
    }
    $fileValidator = \Drupal::service('file.validator');
    if (empty($fileValidator->validate($file, ['extensions' => 'png jpg jpeg']))) {
      $style = ImageStyle::load('original_m');
      return $style ? $style->buildUrl($file->getFileUri()) : $file->createFileUrl(FALSE);
    }
    return $file->createFileUrl(FALSE);
  }

  /**
   * Build absolute URL for a node.
   */
  private function nodeUrl(Node $node): string {
    $request = $this->requestStack->getCurrentRequest();
    $host = $request ? $request->getSchemeAndHttpHost() : '';
    return $host . $node->toUrl()->toString();
  }

}
