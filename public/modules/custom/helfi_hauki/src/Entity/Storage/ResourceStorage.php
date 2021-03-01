<?php

declare(strict_types = 1);

namespace Drupal\helfi_hauki\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * The entity storage class for hauki resource entities.
 */
final class ResourceStorage extends SqlContentEntityStorage implements ContentEntityStorageInterface {

  /**
   * Loads resources by origin.
   *
   * @param string $source_type_id
   *   The source type id.
   * @param string $origin_id
   *   The origin id.
   *
   * @return \Drupal\helfi_hauki\Entity\Resource[]|null
   *   The results or null.
   */
  public function loadByOrigin(string $source_type_id, string $origin_id) : ? array {
    return $this->loadByProperties([
      'origins.key' => $source_type_id,
      'origins.value' => $origin_id,
    ]);
  }

  /**
   * Loads resources by resource type.
   *
   * @param string $resource_type
   *   The resource type.
   * @param string|null $origin_id
   *   The origin id or null.
   *
   * @return \Drupal\helfi_hauki\Entity\Resource[]|null
   *   The entities.
   */
  public function loadByResourceType(string $resource_type, ?string $origin_id = NULL) : ? array {
    $values = [
      'resource_type' => $resource_type,
    ];

    if ($origin_id) {
      $values['origins.value'] = $origin_id;
    }
    return $this->loadByProperties($values);
  }

}
