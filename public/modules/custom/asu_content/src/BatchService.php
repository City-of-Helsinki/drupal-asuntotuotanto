<?php

namespace Drupal\asu_content;

/**
 * Class BatchService.
 */
class BatchService {

  /**
   * Batch process callback.
   *
   * @param int $id
   *   Id of the batch.
   * @param string $operation_details
   *   Details of the operation.
   * @param object $context
   *   Context for operations.
   */
  static public function processContentAliasUpdate($id, $nodes, &$context) {
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
   * Batch Finished callback.
   *
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   * @param array $operations
   *   Array of operations.
   */
  static public function processContentAliasUpdateFinished($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();

    if ($success) {
      $messenger->addMessage(t('@count results processed.', ['@count' => count($results)]));
    }
  }

}
