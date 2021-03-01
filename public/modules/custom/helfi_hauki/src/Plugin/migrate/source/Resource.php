<?php

declare(strict_types = 1);

namespace Drupal\helfi_hauki\Plugin\migrate\source;

/**
 * Source plugin for retrieving data from Hauki.
 *
 * @MigrateSource(
 *   id = "hauki_resource"
 * )
 */
class Resource extends Hauki {

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return 'HaukiResource';
  }

}
