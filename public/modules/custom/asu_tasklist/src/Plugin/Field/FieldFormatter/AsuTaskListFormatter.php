<?php

namespace Drupal\asu_tasklist\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

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
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $selected_vocabylary_id = $items->getFieldDefinition()->getSettings()['selected_taxonomy_id'];
    $vocabulary = \Drupal::service('entity.repository')->loadEntityByUuid('taxonomy_vocabulary', $selected_vocabylary_id);
    $term_list = [];
    $template = '';

    if (!isset($vocabulary)) {
      return;
    }
    /** @var \Drupal\taxonomy\Entity\Term[] $terms */
    $terms = \Drupal::service('entity_type.manager')->getStorage("taxonomy_term")->loadTree($vocabulary->get('originalId'), 0, 1, TRUE);

    if(!$terms){
      return;
    }

    $count = 0;
    if($items[0]->value){
      $data = unserialize($items[0]->value);
      foreach($data as $d){
        $count += $d['value'];
      }
    }

    $total_task_count = count($terms);
    $template = $this->t('Tasks'). ': '. $count . '/' . $total_task_count ;

    $element = [
      '#type' => 'inline_template',
      '#template' => $template,
    ];
    return $element;
  }

}
