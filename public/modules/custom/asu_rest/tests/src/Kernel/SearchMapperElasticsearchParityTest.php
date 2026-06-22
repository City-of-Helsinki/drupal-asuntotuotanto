<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Kernel;

use Drupal\asu_rest\Service\SearchMapper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Ensures SearchMapper exposes Elasticsearch index fields consumed by Django.
 *
 * Before ASU-1793 Django read the apartment search index directly. These keys
 * must remain present in REST _source payloads for ApartmentDocument parity.
 *
 * @group asu_rest
 */
final class SearchMapperElasticsearchParityTest extends KernelTestBase {

  /**
   * Project fields indexed in search_api.index.apartment and on ApartmentDocument.
   */
  private const PROJECT_PARITY_KEYS = [
    'project_acc_financeofficer',
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
    'project_documents_delivered',
    'project_energy_class',
    'project_estimated_completion_date',
    'project_housing_manager',
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
    'project_site_renter',
    'project_virtual_presentation_url',
    'project_zoning_info',
    'project_zoning_status',
    'project_use_complete_contract',
  ];

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
    'file',
    'link',
    'config_terms',
    'asu_rest',
  ];

  /**
   * The mapper under test.
   *
   * @var \Drupal\asu_rest\Service\SearchMapper
   */
  private SearchMapper $mapper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['node']);

    NodeType::create([
      'type' => 'project',
      'name' => 'Project',
    ])->save();
    NodeType::create([
      'type' => 'apartment',
      'name' => 'Apartment',
    ])->save();

    $this->installMinimalProjectFields();
    $this->installMinimalApartmentFields();

    $this->mapper = $this->container->get('asu_rest.search_mapper');
  }

  /**
   * Project map responses include all Elasticsearch parity keys.
   *
   * - Asserts every legacy index key is present on mapProject output.
   * - Asserts values are returned when set on the project node.
   */
  public function testProjectMapIncludesElasticsearchParityKeys(): void {
    $project = Node::create([
      'type' => 'project',
      'title' => 'Parity project',
      'status' => 1,
      'field_depositary' => 'Example Bank',
      'field_use_complete_contract' => 1,
      'field_roof_material' => 'Tile',
    ]);
    $project->save();

    $mapped = $this->mapper->mapProject($project);

    foreach (self::PROJECT_PARITY_KEYS as $key) {
      $this->assertArrayHasKey($key, $mapped, "Missing parity key: {$key}");
    }
    $this->assertSame('Example Bank', $mapped['project_contract_depositary']);
    $this->assertTrue($mapped['project_use_complete_contract']);
    $this->assertSame('Tile', $mapped['project_roof_material']);
  }

  /**
   * Project_use_complete_contract defaults to FALSE when unset on the project.
   */
  public function testProjectUseCompleteContractDefaultsToFalse(): void {
    $project = Node::create([
      'type' => 'project',
      'title' => 'Standard contract project',
      'status' => 1,
    ]);
    $project->save();

    $mapped = $this->mapper->mapProject($project);

    $this->assertArrayHasKey('project_use_complete_contract', $mapped);
    $this->assertFalse($mapped['project_use_complete_contract']);
  }

  /**
   * Apartment listing map exposes apartment_published from node status.
   */
  public function testApartmentListingIncludesApartmentPublished(): void {
    $project = Node::create([
      'type' => 'project',
      'title' => 'Parent project',
      'status' => 1,
    ]);
    $project->save();

    $apartment = Node::create([
      'type' => 'apartment',
      'title' => 'A 1',
      'status' => 1,
    ]);
    $apartment->save();

    $this->mapper->primeProjectLookupWithKnownProject([$apartment], $project);
    $mapped = $this->mapper->mapApartmentListing($apartment);

    $this->assertArrayHasKey('apartment_published', $mapped);
    $this->assertTrue($mapped['apartment_published']);
    $this->assertArrayHasKey('site_owner', $mapped);
  }

  /**
   * Install string/boolean fields required for parity key presence checks.
   */
  private function installMinimalProjectFields(): void {
    foreach ([
      'field_depositary',
      'field_roof_material',
    ] as $fieldName) {
      FieldStorageConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'node',
        'type' => 'string',
      ])->save();
      FieldConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'node',
        'bundle' => 'project',
        'label' => $fieldName,
      ])->save();
    }

    FieldStorageConfig::create([
      'field_name' => 'field_use_complete_contract',
      'entity_type' => 'node',
      'type' => 'boolean',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_use_complete_contract',
      'entity_type' => 'node',
      'bundle' => 'project',
      'label' => 'Use complete apartment contract',
    ])->save();
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
    ] as $fieldName) {
      FieldStorageConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'node',
        'type' => 'string',
      ])->save();
      FieldConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'node',
        'bundle' => 'apartment',
        'label' => $fieldName,
      ])->save();
    }
  }

}
