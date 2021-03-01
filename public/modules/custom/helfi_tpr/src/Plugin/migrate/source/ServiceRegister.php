<?php

declare(strict_types = 1);

namespace Drupal\helfi_tpr\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\helfi_api_base\Plugin\migrate\source\HttpSourcePluginBase;

/**
 * Source plugin for retrieving data from Tpr.
 *
 * @MigrateSource(
 *   id = "tpr_service_register"
 * )
 */
class ServiceRegister extends HttpSourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The total count.
   *
   * @var int
   */
  protected int $count = 0;

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return 'TprServiceRegister';
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
  public function getIds() {
    return ['id' => ['type' => 'string']];
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

    $fields = [
      'title',
      'description_short',
      'description_long',
    ];

    $processed = 0;

    foreach ($content as $item) {
      $service = [];

      $processed++;
      // Allow number of items to be limited by using an env variable.
      if (($this->getLimit() > 0) && $processed > $this->getLimit()) {
        break;
      }

      foreach (['fi', 'en', 'sv'] as $language) {
        $url = $this->buildCanonicalUrl(sprintf('%s?language=%s', $item['id'], $language));
        $data = $this->getContent($url);

        ['id' => $id] = $data;

        if (!isset($service[$id])) {
          $service[$id] = [];
        }

        foreach ($fields as $field) {
          $data[sprintf('%s_%s', $field, $language)] = $data[$field];
        }
        $service[$id] += $data;
      }
      yield reset($service);
    }
  }

}
