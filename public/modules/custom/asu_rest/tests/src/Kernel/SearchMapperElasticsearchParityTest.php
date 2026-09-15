<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

/**
 * Ensures SearchMapper exposes Elasticsearch index fields consumed by Django.
 *
 * Before ASU-1793 Django read the apartment search index directly. These keys
 * must remain present in REST _source payloads for ApartmentDocument parity.
 *
 * @group asu_rest
 */
final class SearchMapperElasticsearchParityTest extends SearchMapperKernelTestBase {

  /**
   * Project fields indexed in search_api.index.apartment and ApartmentDocument.
   */
  private const PROJECT_PARITY_KEYS = [
    'project_acc_financeofficer',
    'project_acc_salesperson',
    'project_accessibility',
    'project_attachment_urls',
    'project_barred_bank_account',
    'project_completion_date',
    'project_constructor',
    'project_contract_apartment_completion_selection_1',
    'project_contract_apartment_completion_selection_1_date',
    'project_contract_apartment_completion_selection_2',
    'project_contract_apartment_completion_selection_2_end',
    'project_contract_apartment_completion_selection_2_start',
    'project_contract_apartment_completion_selection_3',
    'project_contract_apartment_completion_selection_3_date',
    'project_contract_article_of_association',
    'project_contract_bill_of_sale_terms',
    'project_contract_collateral_type',
    'project_contract_construction_permit_requested',
    'project_contract_customer_document_handover',
    'project_contract_default_collateral',
    'project_contract_depositary',
    'project_contract_estimated_handover_date_end',
    'project_contract_estimated_handover_date_start',
    'project_contract_material_selection_date',
    'project_contract_material_selection_description',
    'project_contract_material_selection_later',
    'project_contract_other_terms',
    'project_contract_repository',
    'project_contract_right_of_occupancy_payment_verification',
    'project_contract_rs_bank',
    'project_contract_transfer_restriction',
    'project_contract_usage_fees',
    'project_control_transferred_when',
    'project_customer_document_handover',
    'project_documents_delivered',
    'project_energy_class',
    'project_estimated_completion_date',
    'project_housing_manager',
    'project_parkingplace_count',
    'project_payment_recipient',
    'project_payment_recipient_final',
    'project_project_manager',
    'project_publication_end_time',
    'project_publication_start_time',
    'project_regular_bank_account',
    'project_roof_material',
    'project_sanitation',
    'project_shareholder_meeting_date',
    'project_shares_transferred_when',
    'project_site_area',
    'project_site_owner',
    'project_site_renter',
    'project_smoke_free',
    'project_virtual_presentation_url',
    'project_zoning_info',
    'project_zoning_status',
    'project_use_complete_contract',
  ];

  /**
   * Apartment keys required by ApartmentDocument that belong on detail maps.
   */
  private const APARTMENT_DETAIL_PARITY_KEYS = [
    'financing_fee_m2',
    'housing_shares',
    'maintenance_fee_m2',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->ensureNodeType('apartment', 'Apartment');
    $this->installMinimalProjectFields();
    $this->installMinimalApartmentFields();
  }

  /**
   * Project map responses include all Elasticsearch parity keys.
   *
   * - Asserts every legacy index key is present on mapProject output.
   * - Asserts values are returned when set on the project node.
   */
  public function testProjectMapIncludesElasticsearchParityKeys(): void {
    $mapped = $this->mapProject('Parity project', [
      'field_depositary' => 'Example Bank',
      'field_use_complete_contract' => 1,
      'field_roof_material' => 'Tile',
      'field_acc_salesperson' => 'Maija Myyjä',
      'field_project_accessibility' => 'Elevator and ramp',
      'field_customer_document_handover' => 'Documents at bank',
    ]);

    foreach (self::PROJECT_PARITY_KEYS as $key) {
      $this->assertArrayHasKey($key, $mapped, "Missing parity key: {$key}");
    }
    $this->assertSame('Example Bank', $mapped['project_contract_depositary']);
    $this->assertTrue($mapped['project_use_complete_contract']);
    $this->assertSame('Tile', $mapped['project_roof_material']);
    $this->assertSame('Maija Myyjä', $mapped['project_acc_salesperson']);
    $this->assertSame('Elevator and ramp', $mapped['project_accessibility']);
    $this->assertSame('Documents at bank', $mapped['project_customer_document_handover']);
    $this->assertSame(
      $mapped['project_contract_customer_document_handover'],
      $mapped['project_customer_document_handover'],
    );
  }

  /**
   * Apartment listing exposes project_site_owner alongside site_owner.
   *
   * ApartmentDocument declares both keys; Oikotie/Etuovi read
   * project_site_owner.
   *
   * - Asserts project_site_owner is present on mapApartmentListing.
   * - Asserts it matches site_owner (same field_site_owner source).
   */
  public function testApartmentListingIncludesProjectSiteOwner(): void {
    $mapped = $this->mapApartmentListing('Site owner project', 'A 1');

    $this->assertArrayHasKey('project_site_owner', $mapped);
    $this->assertArrayHasKey('site_owner', $mapped);
    $this->assertSame($mapped['site_owner'], $mapped['project_site_owner']);
  }

  /**
   * Apartment listing/detail expose ApartmentDocument fee/share parity keys.
   *
   * List endpoints feed Etuovi/Oikotie, so these keys must not be detail-only.
   *
   * - Asserts housing_shares, financing_fee_m2, maintenance_fee_m2 on listing.
   * - Asserts detail matches listing for the same apartment.
   * - Asserts housing_shares is derived from stock start/end numbers.
   * - Asserts fee_m2 values are cents-per-m2 from fee / living_area.
   */
  public function testApartmentDetailIncludesFeeAndShareParityKeys(): void {
    $project = $this->createProjectNode('Fee parity project');
    $apartment = $this->createApartmentNode('A 2', [
      'field_stock_start_number' => '10',
      'field_stock_end_number' => '20',
      'field_living_area' => '50',
      'field_financing_fee' => '100',
      'field_maintenance_fee' => '200',
    ]);

    $this->mapper->primeProjectLookupWithKnownProject([$apartment], $project);
    $listed = $this->mapper->mapApartmentListing($apartment);
    $detailed = $this->mapper->mapApartmentDetail($apartment);

    foreach (self::APARTMENT_DETAIL_PARITY_KEYS as $key) {
      $this->assertArrayHasKey($key, $listed, "Missing parity key on listing: {$key}");
      $this->assertArrayHasKey($key, $detailed, "Missing parity key on detail: {$key}");
    }
    $this->assertSame('10 - 20', $listed['housing_shares']);
    // 100 EUR / 50 m2 = 2.00 EUR/m2 => 200 cents; 200 / 50 = 4.00 => 400 cents.
    $this->assertSame(200, $listed['financing_fee_m2']);
    $this->assertSame(400, $listed['maintenance_fee_m2']);
    $this->assertSame($listed, $detailed);
  }

  /**
   * Project_use_complete_contract defaults to FALSE when unset on the project.
   */
  public function testProjectUseCompleteContractDefaultsToFalse(): void {
    $mapped = $this->mapProject('Standard contract project');

    $this->assertArrayHasKey('project_use_complete_contract', $mapped);
    $this->assertFalse($mapped['project_use_complete_contract']);
  }

  /**
   * Apartment listing map exposes apartment_published from node status.
   */
  public function testApartmentListingIncludesApartmentPublished(): void {
    $mapped = $this->mapApartmentListing('Parent project', 'A 1');

    $this->assertArrayHasKey('apartment_published', $mapped);
    $this->assertTrue($mapped['apartment_published']);
    $this->assertArrayHasKey('site_owner', $mapped);
  }

  /**
   * Apartment listing includes publish_on_etuovi and publish_on_oikotie.
   *
   * List endpoints (/apartments, /projects/{uuid}/apartments) must expose these
   * flags so Django get_apartments() does not default them to None.
   *
   * - Asserts both keys are present on mapApartmentListing.
   * - Asserts TRUE when fields are set and FALSE when unset.
   */
  public function testApartmentListingIncludesPublishOnFlags(): void {
    $project = $this->createProjectNode('Parent project');
    $published = $this->createApartmentNode('Published on portals', [
      'field_publish_on_etuovi' => 1,
      'field_publish_on_oikotie' => 1,
    ]);
    $unpublished = $this->createApartmentNode('Not published on portals', [
      'field_publish_on_etuovi' => 0,
      'field_publish_on_oikotie' => 0,
    ]);

    $this->mapper->primeProjectLookupWithKnownProject(
      [$published, $unpublished],
      $project
    );

    $mappedPublished = $this->mapper->mapApartmentListing($published);
    $this->assertArrayHasKey('publish_on_etuovi', $mappedPublished);
    $this->assertArrayHasKey('publish_on_oikotie', $mappedPublished);
    $this->assertTrue($mappedPublished['publish_on_etuovi']);
    $this->assertTrue($mappedPublished['publish_on_oikotie']);

    $mappedUnpublished = $this->mapper->mapApartmentListing($unpublished);
    $this->assertArrayHasKey('publish_on_etuovi', $mappedUnpublished);
    $this->assertArrayHasKey('publish_on_oikotie', $mappedUnpublished);
    $this->assertFalse($mappedUnpublished['publish_on_etuovi']);
    $this->assertFalse($mappedUnpublished['publish_on_oikotie']);
  }

  /**
   * Map a listing payload for a new apartment on a new project.
   *
   * @param string $projectTitle
   *   The project title.
   * @param string $apartmentTitle
   *   The apartment title.
   * @param array<string, mixed> $apartmentValues
   *   Additional apartment field values.
   *
   * @return array<string, mixed>
   *   Mapped apartment listing payload.
   */
  private function mapApartmentListing(
    string $projectTitle,
    string $apartmentTitle,
    array $apartmentValues = [],
  ): array {
    $project = $this->createProjectNode($projectTitle);
    $apartment = $this->createApartmentNode($apartmentTitle, $apartmentValues);
    $this->mapper->primeProjectLookupWithKnownProject([$apartment], $project);
    return $this->mapper->mapApartmentListing($apartment);
  }

  /**
   * Install string/boolean fields required for parity key presence checks.
   */
  private function installMinimalProjectFields(): void {
    foreach ([
      'field_depositary',
      'field_roof_material',
      'field_acc_salesperson',
      'field_project_accessibility',
      'field_customer_document_handover',
    ] as $fieldName) {
      $this->createNodeField($fieldName, 'project', 'string', $fieldName);
    }

    $this->createNodeField(
      'field_use_complete_contract',
      'project',
      'boolean',
      'Use complete apartment contract',
    );
  }

  /**
   * Install apartment bundle fields used by listing map.
   */
  private function installMinimalApartmentFields(): void {
    foreach ([
      'field_apartment_number',
      'field_apartment_structure',
      'field_floor',
      'field_floor_max',
      'field_living_area',
      'field_sales_price',
      'field_debt_free_sales_price',
      'field_release_payment',
      'field_right_of_occupancy_payment',
      'field_stock_start_number',
      'field_stock_end_number',
      'field_financing_fee',
      'field_maintenance_fee',
    ] as $fieldName) {
      $this->createNodeField($fieldName, 'apartment', 'string', $fieldName);
    }

    foreach ([
      'field_publish_on_etuovi',
      'field_publish_on_oikotie',
    ] as $fieldName) {
      $this->createNodeField($fieldName, 'apartment', 'boolean', $fieldName);
    }
  }

}
