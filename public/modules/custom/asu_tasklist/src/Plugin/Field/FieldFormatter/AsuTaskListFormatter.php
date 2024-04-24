<?php

namespace Drupal\asu_tasklist\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository) {
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
