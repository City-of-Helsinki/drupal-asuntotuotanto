<?php

namespace Drupal\asu_content\Commands;

use Drupal\node\Entity\NodeType;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 *
 * @package Drupal\asu_content\Commands
 */
class AsuContentDrushCommands extends DrushCommands {

  /**
   * Create content aliases automatically.
   *
   * @param string $type
   *   The content type.
   *
   * @command asu_content:content-alias-create
   */
  #[CLI\Command(name: 'asu_content:ContentAliasCreate', aliases: ['ac-cac'])]
  #[CLI\Argument(name: 'type', description: 'Content type.')]
  #[CLI\Usage(name: 'drush ac-cac', description: 'Create node alias automatically to content type.')]
  public function ContentAliasCreate(string $type) {
    $node_types = array_keys(NodeType::loadMultiple());

    if (!in_array($type, $node_types)) {
      $this->output()->writeln('Content type doesnt exists');
      return;
    }

    // Create the operations array for the batch.
    $operations = [];
    $num_operations = 0;
    $batch_id = 1;

    $database = \Drupal::database();
    $query = $database->select('node_field_data', 'd');
    $query->condition('d.type', $type);
    $query->fields('d', ['nid']);
    $results_count = count($query->execute()->fetchAll());

    if ($results_count > 0) {
      for ($i = 0; $i < $results_count; $i = $i + 100) {
        $query->range($i, 100);
        $results = $query->execute()->fetchAll();

        // Prepare the operation. Here we could do other operations on nodes.
        $this->output()->writeln("Preparing batch: " . $batch_id);

        $operations[] = [
          '\Drupal\asu_content\BatchService::processContentAliasUpdate',
          [
            $batch_id,
            $results,
          ],
        ];

        $batch_id++;
        $num_operations++;
      }

      // Create the batch.
      $batch = [
        'title' => t('Updating @num node aliases', ['@num' => $num_operations]),
        'operations' => $operations,
        'finished' => '\Drupal\asu_content\BatchService::processContentAliasUpdateFinished',
      ];

      // Add batch operations as new batch sets.
      batch_set($batch);

      // Process the batch sets.
      drush_backend_batch_process();

      // Show some information.
      $this->logger()->notice("Batch operations end.");
    }
  }

}
