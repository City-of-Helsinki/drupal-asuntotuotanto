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
   * Configuration.
   *
   * @var array|\Drupal\Core\Config\ImmutableConfig
   */
  private ?array $config;

  /**
   * Constructor.
   */
  public function __construct() {

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
      $values[$value['external_field']] = $this->get($field) ?? '';
    }
    return $values;
  }

}
