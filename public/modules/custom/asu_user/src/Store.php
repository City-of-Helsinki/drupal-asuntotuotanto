<?php

namespace Drupal\asu_user;

use Drupal\Core\TempStore\PrivateTempStore;

/**
 * Temporary data store.
 */
class Store {

  /**
   * Temporary store.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  private PrivateTempStore $store;

  /**
   * @var array|\Drupal\Core\Config\ImmutableConfig
   */
  private ?array $config;

  /**
   * Constructor.
   */
  public function __construct() {
    $this->store = \Drupal::service('tempstore.private')->get('user');
    $this->config = \Drupal::config('asu_user.external_user_fields')->get('external_data_map');
  }

  /**
   * Get value from datastore.
   *
   * @param string $key
   *   Key for the value.
   *
   * @return string
   *   A value for the key.
   */
  public function get(string $key): ?string {
    return $this->store->get($key);
  }

  /**
   * Set value to datastore.
   *
   * @param string $key
   *   The Key for the value.
   * @param string $value
   *   The value for the key.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function set(string $key, string $value) {
    $this->store->set($key, $value);
  }

  /**
   * Get all user fields from database.
   *
   * @return array
   *   Array of field values keyed by external field name.
   */
  public function getExternalUserData(): array {
    $values = [];
    foreach ($this->config as $field => $value) {
      $values[$value['external_field']] = $this->get($field) ?? '-';
    }
    return $values;
  }

  /**
   * Set multiple values to store by configuration.
   *
   * @param array $fields
   * @param array $data
   */
  public function setMultipleByConfiguration(array $data) {
    if ($this->config) {
      foreach ($data as $key => $value) {
        // Get the index number of configuration by external field name.
        // Only set store-values if they are configured no matter what API returns.
        $fieldNumber = array_search($key, array_column($this->config, 'external_field'));
        if (!is_bool($fieldNumber) && isset(array_keys($this->config)[$fieldNumber])) {
          // Set the value to store by internal filed name.
          $this->store->set(array_keys($this->config)[$fieldNumber], $value);
        }
      }
    }
  }

}
