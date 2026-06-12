<?php

declare(strict_types=1);

namespace Drupal\asu_rest\Service;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\Validation\FileValidatorInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Maps nodes to Elasticsearch-like _source payloads.
 */
final class SearchMapper {

  /**
   * In-request cache for apartment to project mapping.
   *
   * @var array<int,\Drupal\node\Entity\Node|null>
   */
  private array $apartmentProjectMap = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly RequestStack $requestStack,
    private readonly FileValidatorInterface $fileValidator,
    private readonly EntityRepositoryInterface $entityRepository,
  ) {
  }

  /**
   * Map an apartment for list responses.
   */
  public function mapApartmentListing(Node $apartment): array {
    $project = $this->getProjectForApartment($apartment);

    // When project is known, bypass expensive computed fields that use
    // getReverseReferences (asu_computed_apartment_images,
    // multiple_values_field).
    $imageUrls = $project
      ? $this->getApartmentImageUrlsFromProject($apartment, $project)
      : $this->getComputedList($apartment, 'asu_computed_apartment_images');
    $services = $project
      ? $this->getServicesFromProject($project)
      : $this->getComputedList($apartment, 'multiple_values_field');

    $data = [
      '_language' => $apartment->language()->getId(),
      'apartment_published' => $apartment->isPublished(),
      'apartment_address' => $this->getComputedMarkup($apartment, 'field_apartment_address'),
      'apartment_number' => $this->getScalar($apartment, 'field_apartment_number'),
      'apartment_state_of_sale' => $this->getEnumFromTermField($apartment, 'field_apartment_state_of_sale'),
      'apartment_structure' => $this->getScalar($apartment, 'field_apartment_structure'),
      'has_balcony' => $this->getBoolean($apartment, 'field_has_balcony'),
      'has_terrace' => $this->getBoolean($apartment, 'field_has_terrace'),
      'has_yard' => $this->getBoolean($apartment, 'field_has_yard'),
      'has_apartment_sauna' => $this->getBoolean($apartment, 'field_has_apartment_sauna'),
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
      'image_urls' => $imageUrls,
      'services' => $services,
    ];

    if ($project) {
      $data += $this->mapProjectFields($project, $apartment);
      $data['apartment_holding_type'] = $data['project_holding_type'] ?? '';
      $data['site_owner'] = $this->getTermLabel($project, 'field_site_owner');
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
   * Prime apartment to project lookups for current page items.
   *
   * @param \Drupal\node\Entity\Node[] $apartments
   *   Apartments to preload project relations for.
   */
  public function primeProjectLookup(array $apartments): void {
    $apartmentIds = [];
    foreach ($apartments as $apartment) {
      if (!$apartment instanceof Node) {
        continue;
      }
      $id = (int) $apartment->id();
      if (!array_key_exists($id, $this->apartmentProjectMap)) {
        $apartmentIds[] = $id;
      }
    }

    if (!$apartmentIds) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $projectIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'project')
      ->condition('field_apartments', $apartmentIds, 'IN')
      ->execute();

    foreach ($apartmentIds as $apartmentId) {
      $this->apartmentProjectMap[$apartmentId] = NULL;
    }
    if (!$projectIds) {
      return;
    }

    $apartmentIdSet = array_fill_keys($apartmentIds, TRUE);
    $projects = $storage->loadMultiple($projectIds);
    foreach ($projects as $project) {
      if (!$project instanceof Node) {
        continue;
      }
      if (!$project->hasField('field_apartments') || $project->get('field_apartments')->isEmpty()) {
        continue;
      }
      foreach ($project->get('field_apartments')->getValue() as $item) {
        if (!isset($item['target_id'])) {
          continue;
        }
        $targetId = (int) $item['target_id'];
        if (isset($apartmentIdSet[$targetId])) {
          $this->apartmentProjectMap[$targetId] = $project;
        }
      }
    }
  }

  /**
   * Prime project lookup when the project is already known.
   *
   * Use this for project-scoped endpoints where all apartments belong to the
   * same project. Avoids the reverse lookup query used by primeProjectLookup.
   *
   * @param \Drupal\node\Entity\Node[] $apartments
   *   Apartments on the current page.
   * @param \Drupal\node\Entity\Node $project
   *   The project all apartments belong to.
   */
  public function primeProjectLookupWithKnownProject(array $apartments, Node $project): void {
    foreach ($apartments as $apartment) {
      if ($apartment instanceof Node) {
        $this->apartmentProjectMap[(int) $apartment->id()] = $project;
      }
    }
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
      'project_holding_type' => $this->getEnumFromTermField($project, 'field_holding_type'),
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
      'project_postal_code' => $this->getScalar($project, 'field_postal_code'),
      'project_contract_business_id' => $this->getScalar($project, 'field_business_id'),
      'project_realty_id' => $this->getScalar($project, 'field_realty_id'),
      'project_property_number' => $this->getScalar($project, 'field_property_number'),
      'project_new_housing' => $this->getBoolean($project, 'field_new_housing'),
      'project_use_complete_contract' => $this->getBoolean($project, 'field_use_complete_contract'),
      'project_construction_year' => $this->getScalar($project, 'field_construction_year'),
      'project_has_elevator' => $this->getBoolean($project, 'field_has_elevator'),
      'project_has_sauna' => $this->getBoolean($project, 'field_has_sauna'),
      'project_estate_agent' => $this->getReferencedUserField($project, 'field_salesperson', 'field_full_name'),
      'project_estate_agent_email' => $this->getReferencedUserField($project, 'field_salesperson', 'mail'),
      'project_estate_agent_phone' => $this->getReferencedUserField($project, 'field_salesperson', 'field_phone_number'),
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
      'project_published' => $project->isPublished(),
      'project_acc_financeofficer' => $this->getScalar($project, 'field_acc_financeofficer'),
      'project_attachment_urls' => $this->getLinkUrlsFromField($project, 'field_attachments_url'),
      'project_barred_bank_account' => $this->getScalar($project, 'field_barred_bank_account'),
      'project_completion_date' => $this->formatDateTime($this->getScalar($project, 'field_completion_date')),
      'project_constructor' => $this->getScalar($project, 'field_constructor'),
      'project_control_transferred_when' => $this->getScalar($project, 'field_control_transferred_when'),
      'project_documents_delivered' => $this->getScalar($project, 'field_documents_delivered'),
      'project_energy_class' => $this->getTermLabel($project, 'field_energy_class'),
      'project_estimated_completion_date' => $this->formatDateTime($this->getScalar($project, 'field_estimated_completion_date')),
      'project_housing_manager' => $this->getScalar($project, 'field_housing_manager'),
      'project_payment_recipient' => $this->getScalar($project, 'field_payment_recipient'),
      'project_payment_recipient_final' => $this->getScalar($project, 'field_payment_recipient_final'),
      'project_project_manager' => $this->getScalar($project, 'field_project_manager'),
      'project_publication_end_time' => $this->formatDateTime($this->getScalar($project, 'field_publication_end_time')),
      'project_publication_start_time' => $this->formatDateTime($this->getScalar($project, 'field_publication_start_time')),
      'project_regular_bank_account' => $this->getScalar($project, 'field_regular_bank_account'),
      'project_roof_material' => $this->getScalar($project, 'field_roof_material'),
      'project_sanitation' => $this->getScalar($project, 'field_sanitation'),
      'project_shareholder_meeting_date' => $this->formatDateTime($this->getScalar($project, 'field_shareholder_meeting_date')),
      'project_shares_transferred_when' => $this->getScalar($project, 'field_shares_transferred_when'),
      'project_site_area' => $this->getScalar($project, 'field_site_area'),
      'project_site_renter' => $this->getScalar($project, 'field_site_renter'),
      'project_virtual_presentation_url' => $this->getScalar($project, 'field_virtual_presentation_url'),
      'project_zoning_info' => $this->getScalar($project, 'field_zoning_info'),
      'project_zoning_status' => $this->getScalar($project, 'field_zoning_status'),
      'project_contract_apartment_completion_selection_1' => $this->getBoolean($project, 'field_completion_selection_1'),
      'project_contract_apartment_completion_selection_1_date' => $this->formatDateTime($this->getScalar($project, 'field_completion_1_start')),
      'project_contract_apartment_completion_selection_2' => $this->getBoolean($project, 'field_completion_selection_2'),
      'project_contract_apartment_completion_selection_2_start' => $this->formatDateTime($this->getScalar($project, 'field_completion_2_start')),
      'project_contract_apartment_completion_selection_2_end' => $this->formatDateTime($this->getScalar($project, 'field_completion_2_end')),
      'project_contract_apartment_completion_selection_3' => $this->getBoolean($project, 'field_completion_selection_3'),
      'project_contract_apartment_completion_selection_3_date' => $this->formatDateTime($this->getScalar($project, 'field_completion_3_start')),
      'project_contract_article_of_association' => $this->getScalar($project, 'field_article_of_association'),
      'project_contract_bill_of_sale_terms' => $this->getScalar($project, 'field_contract_other_terms'),
      'project_contract_collateral_type' => $this->getScalar($project, 'field_collateral_type'),
      'project_contract_construction_permit_requested' => $this->formatDateTime($this->getScalar($project, 'field_construction_permit_claim')),
      'project_contract_customer_document_handover' => $this->getScalar($project, 'field_customer_document_handover'),
      'project_contract_default_collateral' => $this->getScalar($project, 'field_default_collateral'),
      'project_contract_depositary' => $this->getScalar($project, 'field_depositary'),
      'project_contract_estimated_handover_date_end' => $this->formatDateTime($this->getScalar($project, 'field_estimated_handover_end')),
      'project_contract_estimated_handover_date_start' => $this->formatDateTime($this->getScalar($project, 'field_estimated_handover_start')),
      'project_contract_material_selection_date' => $this->formatDateTime($this->getScalar($project, 'field_material_selection_date')),
      'project_contract_material_selection_description' => $this->getScalar($project, 'field_material_selection_desc'),
      'project_contract_material_selection_later' => $this->getBoolean($project, 'field_material_selection_later'),
      'project_contract_other_terms' => $this->getScalar($project, 'field_other_terms'),
      'project_contract_repository' => $this->getScalar($project, 'field_repository'),
      'project_contract_right_of_occupancy_payment_verification' => $this->getScalar($project, 'field_payment_verification'),
      'project_contract_rs_bank' => $this->getScalar($project, 'field_recommended_bank'),
      'project_contract_transfer_restriction' => $this->getBoolean($project, 'field_transfer_restriction'),
      'project_contract_usage_fees' => $this->getScalar($project, 'field_usage_fees'),
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
    $apartmentId = (int) $apartment->id();
    if (!array_key_exists($apartmentId, $this->apartmentProjectMap)) {
      $this->primeProjectLookup([$apartment]);
    }
    $project = $this->apartmentProjectMap[$apartmentId] ?? NULL;
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
    $field = $entity->get($fieldName);
    $referenced = $field instanceof EntityReferenceFieldItemListInterface
      ? $field->referencedEntities()
      : [];
    $term = reset($referenced);
    return $term ? $term->label() : '';
  }

  /**
   * Get term labels from a multi-value field.
   */
  private function getTermLabels(Node $entity, string $fieldName): array {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return [];
    }
    $field = $entity->get($fieldName);
    if (!$field instanceof EntityReferenceFieldItemListInterface) {
      return [];
    }
    $labels = [];
    foreach ($field->referencedEntities() as $term) {
      $labels[] = $term->label();
    }
    return $labels;
  }

  /**
   * Get enum value from term field.
   */
  private function getEnumFromTermField(Node $entity, string $fieldName): string {
    // Prefer the current translation's value (so we don't "lose" a value that
    // was only set for a translated node). Fall back to the untranslated value.
    $source = $entity;
    if (!$source->hasField($fieldName) || $source->get($fieldName)->isEmpty()) {
      if ($entity instanceof TranslatableInterface) {
        $untranslated = $entity->getUntranslated();
        if ($untranslated->hasField($fieldName) && !$untranslated->get($fieldName)->isEmpty()) {
          $source = $untranslated;
        }
        else {
          return '';
        }
      }
      else {
        return '';
      }
    }
    $field = $source->get($fieldName);
    if (!$field instanceof EntityReferenceFieldItemListInterface) {
      return '';
    }
    $referenced = $field->referencedEntities();
    $refEntity = reset($referenced);
    if (!$refEntity) {
      return '';
    }

    return $this->enumFromReferencedEntity($refEntity);

  }

  /**
   * Resolve a referenced term/config entity to a normalized enum string.
   *
   * Used for apartment_state_of_sale, project_holding_type,
   * project_building_type, project_new_development_status, and
   * project_state_of_sale. Matches computed field plugins: prefer
   * field_machine_readable_name, else English term label.
   */
  private function enumFromReferencedEntity(object $refEntity): string {
    if ($refEntity instanceof TranslatableInterface) {
      $refEntity = $refEntity->getUntranslated();
    }

    // Prefer machine IDs for config entity references (e.g. config_terms_term).
    if ($refEntity instanceof ConfigEntityInterface) {
      return $this->normalizeEnum((string) $refEntity->id());
    }

    // Taxonomy term or other fieldable entity reference.
    if ($refEntity instanceof FieldableEntityInterface
      && $refEntity->hasField('field_machine_readable_name')
      && !$refEntity->get('field_machine_readable_name')->isEmpty()) {
      return $this->normalizeEnum(
        (string) $refEntity->get('field_machine_readable_name')->value,
      );
    }

    if ($refEntity instanceof TermInterface) {
      $term = $this->entityRepository->getTranslationFromContext($refEntity, 'en');
      $name = trim($term->getName());
      if ($name !== '') {
        return $this->normalizeEnum($name);
      }
    }

    return '';
  }

  /**
   * Get lowercase term name for ownership types.
   */
  private function getLowercaseTermName(Node $entity, string $fieldName): string {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return '';
    }
    $field = $entity->get($fieldName);
    if (!$field instanceof EntityReferenceFieldItemListInterface) {
      return '';
    }
    $referenced = $field->referencedEntities();
    $term = reset($referenced);
    if (!$term) {
      return '';
    }
    $value = $term->label();
    return strtolower((string) $value);
  }

  /**
   * Get a field value from an entity-referenced user.
   */
  private function getReferencedUserField(Node $entity, string $refFieldName, string $userFieldName): string {
    if (!$entity->hasField($refFieldName) || $entity->get($refFieldName)->isEmpty()) {
      return '';
    }
    $field = $entity->get($refFieldName);
    if (!$field instanceof EntityReferenceFieldItemListInterface) {
      return '';
    }
    $users = $field->referencedEntities();
    $user = reset($users);
    if (!$user instanceof FieldableEntityInterface || !$user->hasField($userFieldName) || $user->get($userFieldName)->isEmpty()) {
      return '';
    }

    return (string) $user->get($userFieldName)->value;
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
   * Get URIs from a multi-value link field.
   */
  private function getLinkUrlsFromField(Node $entity, string $fieldName): array {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return [];
    }
    $urls = [];
    foreach ($entity->get($fieldName)->getValue() as $item) {
      if (!empty($item['uri'])) {
        $urls[] = (string) $item['uri'];
      }
    }
    return $urls;
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
   *
   * @param int $fileId
   *   File entity ID.
   * @param string $imageStyle
   *   Image style name for image files, or empty to skip style.
   */
  private function buildFileUrl(int $fileId, string $imageStyle = 'original_m'): ?string {
    $file = $this->entityTypeManager->getStorage('file')->load($fileId);
    if (!$file) {
      return NULL;
    }

    // The 'extensions' property must be a string, not an array.
    $extensions = 'png jpg jpeg';
    $violations = $this->fileValidator->validate($file, [
      'FileExtension' => ['extensions' => $extensions],
    ]);

    if ($violations->count() === 0 && $imageStyle !== '') {
      $style = $this->entityTypeManager->getStorage('image_style')->load($imageStyle);
      return $style ? $style->buildUrl($file->getFileUri()) : $file->createFileUrl(FALSE);
    }
    return $file->createFileUrl(FALSE);
  }

  /**
   * Build apartment image URLs from raw fields using known project.
   *
   * Bypasses asu_computed_apartment_images to avoid getReverseReferences.
   * Matches ApartmentImages logic: floorplan first, then shared, then
   * apartment.
   */
  private function getApartmentImageUrlsFromProject(Node $apartment, Node $project): array {
    $urls = [];
    $style = '3_2_m';

    $hasShared = $project->hasField('field_shared_apartment_images')
      && !$project->get('field_shared_apartment_images')->isEmpty();
    $shared = $hasShared
      ? $project->get('field_shared_apartment_images')->getValue()
      : [];
    $hasAptImages = $apartment->hasField('field_images')
      && !$apartment->get('field_images')->isEmpty();
    $apartmentImages = $hasAptImages
      ? $apartment->get('field_images')->getValue()
      : [];
    $hasFloorplan = $apartment->hasField('field_floorplan')
      && !$apartment->get('field_floorplan')->isEmpty();
    $floorplan = $hasFloorplan
      ? $apartment->get('field_floorplan')->getValue()
      : [];

    $items = array_merge($floorplan, $shared, $apartmentImages);
    foreach ($items as $item) {
      if (empty($item['target_id'])) {
        continue;
      }
      if ($url = $this->buildFileUrl((int) $item['target_id'], $style)) {
        $urls[] = $url;
      }
    }
    return $urls;
  }

  /**
   * Build services list from project field_services.
   *
   * Bypasses multiple_values_field to avoid getReverseReferences.
   */
  private function getServicesFromProject(Node $project): array {
    if (!$project->hasField('field_services') || $project->get('field_services')->isEmpty()) {
      return [];
    }
    $fieldServices = $project->get('field_services')->getValue();
    $fieldServices = array_filter($fieldServices, static fn ($s) => !empty($s['term_id']) && $s['term_id'] !== '0');
    $fieldServices = array_values($fieldServices);
    if (empty($fieldServices)) {
      return [];
    }
    $termIds = array_column($fieldServices, 'term_id');
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadMultiple($termIds);
    $result = [];
    foreach ($fieldServices as $svc) {
      $termId = $svc['term_id'];
      $term = $terms[$termId] ?? NULL;
      $distance = $svc['distance'] ?? '';
      if ($term) {
        $result[] = trim($term->label() . ' ' . $distance . 'm');
      }
    }
    return $result;
  }

  /**
   * Build absolute URL for a node.
   *
   * Uses ASU_ASUNTOTUOTANTO_URL when set to avoid internal hostnames in URLs
   * when requests arrive via proxy or internal routing.
   */
  private function nodeUrl(Node $node): string {
    $baseUrl = getenv('ASU_ASUNTOTUOTANTO_URL');
    if ($baseUrl) {
      return rtrim($baseUrl, '/') . $node->toUrl()->toString();
    }
    $request = $this->requestStack->getCurrentRequest();
    $host = $request ? $request->getSchemeAndHttpHost() : '';

    return $host . $node->toUrl()->toString();
  }

}
