<?php

declare(strict_types = 1);

namespace Drupal\helfi_tpr\Plugin\migrate\destination;

use Drupal\helfi_api_base\Plugin\migrate\destination\TranslatableEntityBase;

/**
 * Provides a destination plugin for Tpr entities.
 *
 * @MigrateDestination(
 *   id = "tpr_unit",
 * )
 */
final class Unit extends TranslatableEntityBase {

  /**
   * {@inheritdoc}
   */
  protected function getTranslatableFields(): array {
    return [
      'name' => 'name',
      'call_charge_info' => 'call_charge_info',
      'www' => 'www/uri',
      'address_postal_full' => 'address_postal',
      'street_address' => 'address/address_line1',
      'address_city' => 'address/locality',
      'desc' => 'description/value',
      'short_desc' => 'description/summary',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getEntityTypeId($plugin_id) {
    return 'tpr_unit';
  }

}
