<?php

namespace Drupal\asu_content\Plugin\Field\FieldWidget;

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
 *   id = "asu_service_widget",
 *   label = @Translation("Services widget"),
 *   field_types = {
 *     "asu_services",
 *   }
 * )
 */
class AsuServiceWidget extends WidgetBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository service.
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
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $selected_vocabylary_id = $items[$delta]->getFieldDefinition()->getSettings()['selected_taxonomy_id'];
    $vocabulary = $this->entityRepository->loadEntityByUuid('taxonomy_vocabulary', $selected_vocabylary_id);
    $term_list = [$this->t('Select service')];

    if (isset($vocabulary)) {
      /** @var \Drupal\taxonomy\Entity\Term[] $terms */
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vocabulary->get('originalId'), 0, 1, TRUE);
      foreach ($terms as $term) {
        $term_list[$term->id()] = $term->getName();
      }
    }

    $term_id = $items[$delta]->term_id ?? 0;
    $distance = $items[$delta]->distance ?? 0;

    $elements['term_id'] = [
      '#type' => 'select',
      '#options' => $term_list,
      '#title' => $this->t('Service'),
      '#default_value' => $term_id,
    ];

    $elements['distance'] = [
      '#type' => 'number',
      '#title' => $this->t('Distance'),
      '#default_value' => $distance,
    ];

    $element += $elements;
    return $element;
    // Return ['value' => $element];.
  }

}
