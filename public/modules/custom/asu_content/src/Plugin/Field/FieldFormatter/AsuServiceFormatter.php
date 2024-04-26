<?php

namespace Drupal\asu_content\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the Tasklist formatter.
 *
 * @FieldFormatter(
 *   id = "asu_service_formatter",
 *   label = @Translation("Service formatter"),
 *   field_types = {
 *     "asu_services"
 *   }
 * )
 */
class AsuServiceFormatter extends FormatterBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['label'], $configuration['view_mode'], $configuration['third_party_settings']);
    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    foreach ($items as $delta => $item) {
      if ($term = $this->entityTypeManager->getStorage('taxonomy_term')->load($item->get('term_id')->getValue())) {
        $term_name = $term->getName();
        $distance = $item->get('distance')->getValue();
        $element[$delta] = ['#markup' => "$term_name $distance m"];
      }
    }
    return $element;
  }

}
