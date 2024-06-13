<?php

namespace Drupal\asu_tasklist\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the Tasklist formatter.
 *
 * @FieldFormatter(
 *   id = "tasklist_formatter",
 *   label = @Translation("Tasklist formatter"),
 *   field_types = {
 *     "asu_tasklist"
 *   }
 * )
 */
class AsuTaskListFormatter extends FormatterBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['label'], $configuration['view_mode'], $configuration['third_party_settings']);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityRepository = $container->get('entity.repository');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $selected_vocabylary_id = $items->getFieldDefinition()->getSettings()['selected_taxonomy_id'];
    $vocabulary = $this->entityRepository->loadEntityByUuid('taxonomy_vocabulary', $selected_vocabylary_id);
    $template = '';

    if (!isset($vocabulary)) {
      return [];
    }
    /** @var \Drupal\taxonomy\Entity\Term[] $terms */
    $terms = $this->entityTypeManager->getStorage("taxonomy_term")->loadTree($vocabulary->get('originalId'), 0, 1, TRUE);

    if (!$terms) {
      return [];
    }

    $count = 0;
    if (isset($items[0]) && $items[0]->value) {
      $data = unserialize($items[0]->value, ['allowed_classes' => FALSE]);
      foreach ($data as $values) {
        $count += $values['value'];
      }
    }

    $task_string = $this->t('Tasks');
    $total_task_count = count($terms);
    $template = $task_string . ': ' . $count . '/' . $total_task_count;

    $element = [
      '#type' => 'inline_template',
      '#template' => $template,
    ];
    return $element;
  }

}
