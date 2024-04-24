<?php

namespace Drupal\asu_tasklist\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A widget bar.
 *
 * @FieldWidget(
 *   id = "tasklist_widget",
 *   label = @Translation("Tasklist widget"),
 *   field_types = {
 *     "asu_tasklist",
 *   }
 * )
 */
class AsuTaskListWidget extends WidgetBase {

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
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository,) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository  = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Add tasklist javascript to the form.
    $form['#attached']['library'][] = 'asu_tasklist/tasklist';

    $selected_vocabylary_id = $items[$delta]->getFieldDefinition()->getSettings()['selected_taxonomy_id'];
    $vocabulary = $this->entityRepository->loadEntityByUuid('taxonomy_vocabulary', $selected_vocabylary_id);
    $term_list = [];

    if (isset($vocabulary)) {
      /** @var \Drupal\taxonomy\Entity\Term[] $terms */
      $terms = $this->entityTypeManager->getStorage("taxonomy_term")->loadTree($vocabulary->get('originalId'), 0, 1, TRUE);
      foreach ($terms as $term) {
        $term_list[$term->id()] = $term->getName();
      }
    }

    $value = $items[$delta]->value ?? FALSE;
    $task_list_values = [];
    if ($value) {
      $task_list_values = unserialize($value, ['allowed_classes' => FALSE]);
    }

    $elements = [];
    foreach ($term_list as $id => $name) {
      $bool = FALSE;
      $description = '';

      if (isset($task_list_values[$id])) {
        $bool = $task_list_values[$id]['value'];
        $description = $task_list_values[$id]['description'];
      }

      $elements["task_$id"] = [
        '#prefix' => '<div class="asu_task_wrapper">',
        '#suffix' => '</div>',
      ];

      $elements["task_$id"]["task:$id"] = [
        '#type' => 'checkbox',
        '#title' => $name,
        '#default_value' => $bool,
      ];

      $elements["task_$id"]["description:$id"] = [
        '#type' => 'textfield',
        '#title' => $this->t('Description'),
        '#title_display' => 'invisible',
        '#placeholder' => $this->t('Description'),
        '#default_value' => $description,
        '#maxlength' => 255,
      ];
    }

    $element += $elements;
    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $data = [];
    foreach ($values[0]['value'] as $key => $task_wrapper) {
      $id = explode('_', $key)[1];
      foreach ($task_wrapper as $field_name => $value) {
        if ($field_name === "task:$id") {
          $data[$id]['tid'] = $id;
          $data[$id]['value'] = $value;
        }
        if ($field_name === "description:$id") {
          $data[$id]['description'] = $data[$id]['value'] ? $value : '';
        }
      }
    }
    return ['value' => serialize($data)];
  }

}
