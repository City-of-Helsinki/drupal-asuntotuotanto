<?php

namespace Drupal\asu_content;

/**
 * Batch service to update node aliases.
 */
class BatchService {

  /**
   * Batch process callback.
   *
   * @param int $id
   *   Id of the batch.
   * @param array $nodes
   *   Nodes for batch.
   * @param object $context
   *   Context for operations.
   */
  public static function processContentAliasUpdate($id, $nodes, &$context) {
    foreach ($nodes as $node) {
      usleep(100);
      $entityService = \Drupal::entityTypeManager();
      $entity = $entityService->getStorage('node')->load($node->nid);

      if ($entity) {
        /** @var \Drupal\node\Entity $entity */
        $entity->path->pathauto = 1;
        $entity->save();
        $context['results'][] = $id;
        $context['message'] = t('processing "@id"',
          ['@id' => $id]
        );
      }
    }
  }

  /**
   * Batch process to process occupancy Ppayment.
   */
  public static function processConvertOccupancyPayment($id, $nodes, &$context) {
    foreach ($nodes as $node) {
      usleep(100);
      $entityService = \Drupal::entityTypeManager();
      /** @var \Drupal\asu_content\Entity\Project $project */
      $project = $entityService->getStorage('node')->load($node->nid);

      if ($project) {
        // Get project apartment entities.
        /** @var \Drupal\asu_content\Entity\Apartment $apartment */
        foreach ($project->getApartmentEntities() as $apartment) {
          $field_alteration_work = NULL;
          $field_index_adjusted_right_of_oc = NULL;

          if (!$apartment->get('field_alteration_work')->isEmpty()) {
            $field_alteration_work = $apartment->get('field_alteration_work')->first()->getValue()['value'];

            if ($field_alteration_work == '0.00') {
              $field_alteration_work = NULL;
            }
          }

          if (!$apartment->get('field_index_adjusted_right_of_oc')->isEmpty()) {
            $field_index_adjusted_right_of_oc = $apartment->get('field_index_adjusted_right_of_oc')->first()->getValue()['value'];

            if ($field_index_adjusted_right_of_oc == '0.00') {
              $field_index_adjusted_right_of_oc = NULL;
            }
          }

          if (empty($field_alteration_work) && empty($field_index_adjusted_right_of_oc)) {
            $field_right_of_occupancy_payment = NULL;
            if (!$apartment->get('field_right_of_occupancy_payment')->isEmpty()) {
              $field_right_of_occupancy_payment = $apartment->get('field_right_of_occupancy_payment')->first()->getValue()['value'];
            }

            $apartment->set('field_right_of_occupancy_payment', NULL);
            $apartment->set('field_haso_fee', $field_right_of_occupancy_payment);
            $apartment->save();
          }
        }

        $context['results'][] = $id;
        $context['message'] = t('processing "@id"',
          ['@id' => $id]
        );
      }
    }
  }

  /**
   * Batch Finished callback.
   *
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   * @param array $operations
   *   Array of operations.
   */
  public static function processContentAliasUpdateFinished($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();

    if ($success) {
      $messenger->addMessage(t('@count results processed.', ['@count' => count($results)]));
    }
  }

}
