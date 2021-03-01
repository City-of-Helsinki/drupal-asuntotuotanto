<?php

declare(strict_types = 1);

namespace Drupal\helfi_hauki\Plugin\migrate\destination;

use Drupal\helfi_api_base\Plugin\migrate\destination\TranslatableEntityBase;
use Drupal\migrate\Row;

/**
 * Provides a destination plugin for Hauki resource entities.
 *
 * @MigrateDestination(
 *   id = "hauki_resource",
 * )
 */
final class Resource extends TranslatableEntityBase {

  /**
   * {@inheritdoc}
   */
  protected static function getEntityTypeId($plugin_id) {
    return 'hauki_resource';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTranslatableFields(): array {
    return [
      'name' => 'name',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(Row $row, array $old_destination_id_values) {
    /** @var \Drupal\helfi_hauki\Entity\Resource $entity */
    $entity = parent::getEntity($row, $old_destination_id_values);

    if (!$origins = $row->getSourceProperty('origins')) {
      return $entity;
    }

    foreach ($origins as $origin) {
      if (!isset($origin['data_source']['id'], $origin['origin_id'])) {
        continue;
      }
      $entity->addOrigin($origin['data_source']['id'], $origin['origin_id']);
    }

    return $entity;
  }

}
