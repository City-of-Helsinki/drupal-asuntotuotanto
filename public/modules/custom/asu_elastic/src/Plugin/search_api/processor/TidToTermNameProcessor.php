<?php

namespace Drupal\asu_elastic\Plugin\search_api\processor;

use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Converts taxonomy term IDs to their labels for indexing.
 *
 * @SearchApiProcessor(
 *   id = "tid_to_term_name",
 *   label = @Translation("Term ID to term name"),
 *   description = @Translation("Converts taxonomy term IDs to term labels for indexing"),
 *   stages = {
 *     "preIndex" = 20,
 *   }
 * )
 */
class TidToTermNameProcessor extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items) {
    foreach ($items as $item) {
      $this->processItem($item);
    }
  }

  /**
   * Process a single item to convert term IDs to labels.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The item to process.
   */
  protected function processItem(ItemInterface $item) {
    $fields = $item->getFields();
    $entityTypeManager = \Drupal::entityTypeManager();
    $termStorage = $entityTypeManager->getStorage('taxonomy_term');

    foreach ($fields as $field) {
      $fieldId = $field->getFieldIdentifier();

      // Only process project_district field.
      if ($fieldId !== 'project_district') {
        continue;
      }

      $values = $field->getValues();
      $newValues = [];

      foreach ($values as $value) {
        // If value is numeric, it's a term ID - convert to name.
        if (is_numeric($value)) {
          $term = $termStorage->load($value);
          if ($term) {
            $newValues[] = $term->label();
          }
          else {
            $newValues[] = $value;
          }
        }
        else {
          // Already a string (label), keep as is.
          $newValues[] = $value;
        }
      }

      // Update field with converted values if any changed.
      if (!empty($newValues) && $newValues !== $values) {
        $field->setValues($newValues);
      }
    }
  }

}
