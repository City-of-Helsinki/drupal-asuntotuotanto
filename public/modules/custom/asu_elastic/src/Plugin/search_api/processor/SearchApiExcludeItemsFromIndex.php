<?php

namespace Drupal\asu_elastic\Plugin\search_api\processor;

use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Excludes entities marked as 'excluded' from being indexes.
 *
 * @SearchApiProcessor(
 *   id = "search_api_exclude_items_from_index",
 *   label = @Translation("Search API Exclude Items From Index - Custom Processor"),
 *   description = @Translation("Excludes A0 apartments from being indexed."),
 *   stages = {
 *     "alter_items" = -50
 *   }
 * )
 */
class SearchApiExcludeItemsFromIndex extends ProcessorPluginBase {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {

    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      // Get index data.
      $object = $item->getOriginalObject()->getValue();
      // Get content type.
      $bundle = $object->bundle();

      // Do not index apartment which include A0.
      if ($bundle == 'apartment' && str_contains($object->getTitle(), 'A0')) {
        unset($items[$item_id]);
      }
    }
  }

}
