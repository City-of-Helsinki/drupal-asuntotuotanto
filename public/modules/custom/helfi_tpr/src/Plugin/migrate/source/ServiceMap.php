<?php

declare(strict_types = 1);

namespace Drupal\helfi_tpr\Plugin\migrate\source;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\helfi_api_base\Plugin\migrate\source\HttpSourcePluginBase;

/**
 * Source plugin for retrieving data from Tpr.
 *
 * @MigrateSource(
 *   id = "tpr_service_map"
 * )
 */
class ServiceMap extends HttpSourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The total count.
   *
   * @var int
   */
  protected int $count = 0;

  /**
   * {@inheritdoc}
   */
  protected bool $useRequestCache = FALSE;

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return 'TprServiceMap';
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return ['id' => ['type' => 'string']];
  }

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE) {
    if (!$this->count) {
      $this->count = count($this->getContent($this->configuration['url']));
    }
    return $this->count;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeListIterator() : \Iterator {
    $content = $this->getContent($this->configuration['url']);

    $dates = [];
    // Sort data by modified_time.
    foreach ($content as $key => $item) {
      $dates[$key] = DateTimePlus::createFromFormat('Y-m-d\TH:i:s', $item['modified_time'])->format('U');
    }
    array_multisort($dates, SORT_DESC, $content);

    $processed = 0;

    foreach ($content as $object) {
      // Skip entire migration once we've reached the number of maximum
      // ignored (not changed) rows.
      // @see static::NUM_IGNORED_ROWS_BEFORE_STOPPING.
      if ($this->isPartialMigrate() && ($this->ignoredRows >= static::NUM_IGNORED_ROWS_BEFORE_STOPPING)) {
        break;
      }
      $processed++;

      // Allow number of items to be limited by using an env variable.
      if (($this->getLimit() > 0) && $processed > $this->getLimit()) {
        break;
      }
      yield $object;
    }
  }

}
