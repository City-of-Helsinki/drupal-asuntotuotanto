<?php

declare(strict_types = 1);

namespace Drupal\helfi_tpr\Plugin\migrate\destination;

use Drupal\helfi_api_base\Plugin\migrate\destination\TranslatableEntityBase;
use Drupal\helfi_tpr\Entity\Unit;
use Drupal\migrate\Row;

/**
 * Provides a destination plugin for Tpr service entities.
 *
 * @MigrateDestination(
 *   id = "tpr_service",
 * )
 */
final class Service extends TranslatableEntityBase {

  /**
   * {@inheritdoc}
   */
  protected static function getEntityTypeId($plugin_id) {
    return 'tpr_service';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTranslatableFields(): array {
    return [
      'title' => 'name',
      'description_long' => 'description/value',
      'description_short' => 'description/summary',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(Row $row, array $old_destination_id_values) {
    $entity = parent::getEntity($row, $old_destination_id_values);
    $entity->setData('source', $row->getSource());

    if ($unitIds = $row->getSourceProperty('unit_ids')) {
      $units = Unit::loadMultiple($unitIds);

      array_map(function (Unit $unit) use ($entity) {
        $unit->addService($entity)
          ->save();
      }, $units);
    }

    return $entity;
  }

}
