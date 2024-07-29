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
   * Batch process to process occupancy payment.
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
          $new_field_value = NULL;

          // Get alteration work value if that exist and it's not 0€.
          if (!$apartment->get('field_alteration_work')->isEmpty()) {
            $field_alteration_work = $apartment->get('field_alteration_work')->first()->getValue()['value'];

            if ($field_alteration_work == '0.00') {
              $field_alteration_work = NULL;
            }
          }

          // Get index_adjusted_right_of_oc value if that exist and it's not 0€.
          if (!$apartment->get('field_index_adjusted_right_of_oc')->isEmpty()) {
            $field_index_adjusted_right_of_oc = $apartment->get('field_index_adjusted_right_of_oc')->first()->getValue()['value'];

            if ($field_index_adjusted_right_of_oc == '0.00') {
              $field_index_adjusted_right_of_oc = NULL;
            }
          }

          // If alteration work and index_adjusted_right_of_oc is empty.
          if (empty($field_alteration_work) && empty($field_index_adjusted_right_of_oc)) {
            // Get Haso fee field value if exist.
            if (!$apartment->get('field_haso_fee')->isEmpty()) {
              $new_field_value = $apartment->get('field_haso_fee')->first()->getValue()['value'];
            }

            // First trying to get right_of_occupancy_payment value.
            if (!$apartment->get('field_right_of_occupancy_payment')->isEmpty()) {
              $new_field_value = $apartment->get('field_right_of_occupancy_payment')->first()->getValue()['value'];
            }

            // Check if release_payment field empty.
            if ($apartment->get('field_release_payment')->isEmpty()) {
              // Set HASO fee value to release_payment field
              // if release_payment field is empty.
              $apartment->set('field_release_payment', $new_field_value);
            }

            // Empty right_of_occupancy_payment field.
            $apartment->set('field_right_of_occupancy_payment', NULL);
          }

          // Get release_payment if field right_of_occupancy_payment is empty.
          if ($apartment->get('field_right_of_occupancy_payment')->isEmpty() &&
            !$apartment->get('field_release_payment')->isEmpty()) {
            $new_field_value = $apartment->get('field_release_payment')->first()->getValue()['value'];
          }

          // Case where alteration_work and index_adjusted_right_of_oc
          // is filled but right_of_occupancy_payment value
          // should be in haso fee field.
          if ($apartment->get('field_haso_fee')->isEmpty() &&
            !$apartment->get('field_right_of_occupancy_payment')->isEmpty() &&
            !$apartment->get('field_release_payment')->isEmpty() &&
            (!empty($field_alteration_work) ||
            !empty($field_index_adjusted_right_of_oc))
          ) {
            $occupancy_payment = floatval($apartment->get('field_right_of_occupancy_payment')->first()->getValue()['value']);
            $field_release_payment = $apartment->get('field_release_payment')->first()->getValue()['value'];
            $calculate_release_payment = floatval($field_release_payment - $field_index_adjusted_right_of_oc - $field_alteration_work);

            if ($occupancy_payment === $calculate_release_payment) {
              $new_field_value = $occupancy_payment;
            }

            // Check if release_payment field empty.
            if ($apartment->get('field_release_payment')->isEmpty()) {
              // Set HASO fee value to release_payment field
              // if release_payment field is empty.
              $apartment->set('field_release_payment', $new_field_value);
            }
          }

          // Case where haso fee & release payment is empty.
          if ($apartment->get('field_haso_fee')->isEmpty() &&
            $apartment->get('field_release_payment')->isEmpty() &&
            !empty($field_alteration_work) &&
            !empty($field_index_adjusted_right_of_oc)
          ) {
            // Get Haso fee field value if exist.
            $apartment->set('field_release_payment', $field_index_adjusted_right_of_oc + $field_alteration_work);
            $new_field_value = $field_index_adjusted_right_of_oc - floatval($apartment->get('field_right_of_occupancy_payment')->first()->getValue()['value']);
          }

          // Set a new HASO fee field value.
          if ($apartment->get('field_haso_fee')->isEmpty() && !$apartment->get('field_release_payment')->isEmpty()) {
            if ($new_field_value) {
              $apartment->set('field_haso_fee', $new_field_value);
            }
            // If the new_field_value gives back null then we can
            // calculate the field_haso_fee from given data.
            if (!$apartment->get('field_right_of_occupancy_payment')->isEmpty() &&
              !$apartment->get('field_index_adjusted_right_of_oc')->isEmpty() &&
              ($apartment->get('field_right_of_occupancy_payment')->first()->getValue()['value'] <
              $apartment->get('field_index_adjusted_right_of_oc')->first()->getValue()['value'])
            ) {
              $right_of_occupancy_payment = $apartment->get('field_right_of_occupancy_payment')->first()->getValue()['value'];
              $index_adjusted_right_of_oc = $apartment->get('field_index_adjusted_right_of_oc')->first()->getValue()['value'];
              // Calculating the haso_fee.
              $corrected_haso_fee = $index_adjusted_right_of_oc - $right_of_occupancy_payment;
              $apartment->set('field_haso_fee', $corrected_haso_fee);
            }
          }

          // In cases where field_right_of_occupancy_payment has gotten the
          // value for field_index_adjusted_right_of_oc from the API.
          // field_right_of_occupancy_payment should only be in the
          // thousands and never bigger than the
          // field_index_adjusted_right_of_oc.
          if (!$apartment->get('field_haso_fee')->isEmpty() &&
            !$apartment->get('field_index_adjusted_right_of_oc')->isEmpty() &&
            !$apartment->get('field_right_of_occupancy_payment')->isEmpty() &&
            ($apartment->get('field_right_of_occupancy_payment')->first()->getValue()['value'] >
              $apartment->get('field_index_adjusted_right_of_oc')->first()->getValue()['value'])
          ) {
            $field_haso_fee = $apartment->get('field_haso_fee')->first()->getValue()['value'];
            $original_index_adjusted_right_of_oc = $apartment->get('field_index_adjusted_right_of_oc')->first()->getValue()['value'];
            // Use the field_haso_fee for calculating the right value.
            $corrected_index_adjusted_right_of_oc = $original_index_adjusted_right_of_oc + $field_haso_fee;
            // Moving the wrongly imported value.
            $corrected_right_of_occupancy_payment = $original_index_adjusted_right_of_oc;

            $apartment->set('field_index_adjusted_right_of_oc', $corrected_index_adjusted_right_of_oc);
            $apartment->set('field_right_of_occupancy_payment', $corrected_right_of_occupancy_payment);
          }

          // In case where haso_fee and right_of_occupancy_payment are the same
          // null the value of right_of_occupancy_payment.
          if (!$apartment->get('field_haso_fee')->isEmpty() &&
            !$apartment->get('field_right_of_occupancy_payment')->isEmpty() &&
            $apartment->get('field_haso_fee')->first()->getValue()['value'] ==
            $apartment->get('field_right_of_occupancy_payment')->first()->getValue()['value']
          ) {
            $apartment->set('field_right_of_occupancy_payment', NULL);
          }

          $apartment->save();
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
