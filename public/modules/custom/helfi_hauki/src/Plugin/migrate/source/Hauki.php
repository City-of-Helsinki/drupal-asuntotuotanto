<?php

declare(strict_types = 1);

namespace Drupal\helfi_hauki\Plugin\migrate\source;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\helfi_api_base\Plugin\migrate\source\HttpSourcePluginBase;

/**
 * Source plugin base for retrieving data from Hauki.
 */
abstract class Hauki extends HttpSourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The total count.
   *
   * @var int
   */
  protected int $count = 0;

  /**
   * An array of urls to fetch.
   *
   * @var string[]
   */
  protected array $urls = [];

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
      $this->count = $this->doCount();
    }
    return $this->count;
  }

  /**
   * Builds the metadata.
   */
  protected function buildUrls() : self {
    $this->count();
    $currentUrl = UrlHelper::parse($this->configuration['url']);

    $limit = $currentUrl['query']['page_size'] ?? 100;

    // The api is sorted by oldest item first, start fetching data from the
    // last page to make sure we always get the newest items first.
    for ($i = ceil($this->count / $limit); $i > 0; $i--) {
      $currentUrl['query']['page'] = $i;

      $this->urls[] = Url::fromUri($currentUrl['path'], [
        'query' => $currentUrl['query'],
        'fragment' => $currentUrl['fragment'],
      ])->toString();
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function doCount() :int {
    if ($limit = $this->getLimit()) {
      return $limit;
    }
    $response = $this->getContent($this->configuration['url']);

    return (int) $response['count'] ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [];
  }

  /**
   * Process the item.
   *
   * @param array $item
   *   The item.
   */
  protected function processItem(array &$item) : void {
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeListIterator() : \Iterator {
    $this->buildUrls();

    $processed = 0;

    foreach ($this->urls as $url) {
      $content = $this->getContent($url);

      foreach ($content['results'] as $object) {
        // Skip entire migration once we've reached the number of maximum
        // ignored (not changed) rows.
        // @see static::NUM_IGNORED_ROWS_BEFORE_STOPPING.
        if ($this->isPartialMigrate() && ($this->ignoredRows >= static::NUM_IGNORED_ROWS_BEFORE_STOPPING)) {
          break 2;
        }
        $processed++;

        // Allow number of items to be limited by using an env variable.
        if (($this->getLimit() > 0) && $processed > $this->getLimit()) {
          break 2;
        }
        $this->processItem($object);

        yield $object;
      }
    }
  }

}
