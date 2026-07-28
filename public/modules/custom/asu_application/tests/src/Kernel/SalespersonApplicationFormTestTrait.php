<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\config_terms\Entity\Term;
use Drupal\config_terms\Entity\Vocab;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Shared fixtures for salesperson application form tests.
 */
trait SalespersonApplicationFormTestTrait {

  /**
   * Install project content model used by salesperson project provider.
   */
  protected function installSalespersonProjectContentModel(): void {
    NodeType::create([
      'type' => 'project',
      'name' => 'Project',
    ])->save();

    $this->createStateOfSaleVocabulary();

    $fields = [
      'field_archived' => 'boolean',
      'field_housing_company' => 'string',
      'field_street_address' => 'string',
    ];

    foreach ($fields as $field_name => $type) {
      if (!FieldStorageConfig::loadByName('node', $field_name)) {
        FieldStorageConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'node',
          'type' => $type,
        ])->save();
      }

      if (!FieldConfig::loadByName('node', 'project', $field_name)) {
        FieldConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'node',
          'bundle' => 'project',
          'label' => $field_name,
        ])->save();
      }
    }

    if (!FieldStorageConfig::loadByName('node', 'field_state_of_sale')) {
      FieldStorageConfig::create([
        'field_name' => 'field_state_of_sale',
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'settings' => [
          'target_type' => 'config_terms_term',
        ],
      ])->save();
    }

    if (!FieldConfig::loadByName('node', 'project', 'field_state_of_sale')) {
      FieldConfig::create([
        'field_name' => 'field_state_of_sale',
        'entity_type' => 'node',
        'bundle' => 'project',
        'label' => 'State of sale',
        'settings' => [
          'handler' => 'default:config_terms_term',
        ],
      ])->save();
    }
  }

  /**
   * Create state_of_sale vocabulary terms used in tests.
   */
  protected function createStateOfSaleVocabulary(): void {
    if (!Vocab::load('state_of_sale')) {
      Vocab::create([
        'id' => 'state_of_sale',
        'label' => 'State of sale',
      ])->save();
    }

    foreach (['upcoming', 'ready', 'for_sale', 'processing'] as $term_id) {
      if (!Term::load($term_id)) {
        Term::create([
          'id' => $term_id,
          'vid' => 'state_of_sale',
          'label' => ucfirst(str_replace('_', ' ', $term_id)),
        ])->save();
      }
    }
  }

  /**
   * Create a project node for testing.
   *
   * @param string $housingCompany
   *   Housing company name.
   * @param string $streetAddress
   *   Street address.
   * @param string $stateOfSale
   *   State of sale term id.
   * @param string|null $applicationEndTime
   *   Optional application end time (ISO 8601).
   *
   * @return \Drupal\node\Entity\Node
   *   The created project node.
   */
  protected function createTestProject(
    string $housingCompany,
    string $streetAddress,
    string $stateOfSale,
    ?string $applicationEndTime = NULL,
  ): Node {
    $values = [
      'type' => 'project',
      'title' => $housingCompany,
      'status' => 1,
      'field_archived' => 0,
      'field_housing_company' => $housingCompany,
      'field_street_address' => $streetAddress,
      'field_state_of_sale' => [
        ['target_id' => $stateOfSale],
      ],
    ];
    if ($applicationEndTime !== NULL) {
      $values['field_application_end_time'] = $applicationEndTime;
    }
    $project = Node::create($values);
    $project->save();

    return $project;
  }

  /**
   * Create a customer user for application ownership tests.
   *
   * @return \Drupal\user\Entity\User
   *   The created user.
   */
  protected function createCustomerUser(): User {
    $user = User::create([
      'name' => 'customer-test',
      'mail' => 'customer@example.com',
      'status' => 1,
    ]);
    $user->save();

    return $user;
  }

  /**
   * Installs application end time field on project nodes.
   */
  protected function installApplicationEndTimeField(): void {
    if (!FieldStorageConfig::loadByName('node', 'field_application_end_time')) {
      FieldStorageConfig::create([
        'field_name' => 'field_application_end_time',
        'entity_type' => 'node',
        'type' => 'datetime',
        'settings' => ['datetime_type' => 'datetime'],
      ])->save();
    }

    if (!FieldConfig::loadByName('node', 'project', 'field_application_end_time')) {
      FieldConfig::create([
        'field_name' => 'field_application_end_time',
        'entity_type' => 'node',
        'bundle' => 'project',
        'label' => 'Application end time',
      ])->save();
    }
  }

}
