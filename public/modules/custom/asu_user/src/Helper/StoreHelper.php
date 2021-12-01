<?php

namespace Drupal\asu_user\Helper;

use Drupal\Core\TempStore\PrivateTempStore;

/**
 * Store helper.
 */
class StoreHelper {

  /**
   * Set multiple values to store by configuration.
   *
   * @param Drupal\Core\TempStore\PrivateTempStore $store
   *   Private temp store.
   * @param array $config
   *   Configuration field map.
   * @param array $data
   *   Values to store.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public static function setMultipleValuesToStoreByConfiguration(PrivateTempStore $store, array $config, array $data) {
    foreach ($data as $key => $value) {
      // Get the index number of configuration by external field name.
      // Only store values if they are configured no matter what API returns.
      $fieldNumber = array_search($key, array_column($config, 'external_field'));
      if (!is_bool($fieldNumber) && isset(array_keys($config)[$fieldNumber])) {
        // Set the value to store by internal filed name.
        $store->set(array_keys($config)[$fieldNumber], $value);
      }
    }
  }

}
