<?php

namespace Drupal\asu_content\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 *
 * @package Drupal\asu_content\Commands
 */
class AsuContentDrushCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * Entity type service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new AsuContentDrushCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Connection $connection) {
    $this->entityTypeManager = $entityTypeManager;
    $this->connection = $connection;
  }

  /**
   * Create content aliases automatically.
   *
   * @param string $type
   *   The content type.
   *
   * @command asu_content:content-alias-create
   */
  #[CLI\Command(name: 'asu_content:contentAliasCreate', aliases: ['ac-cac'])]
  #[CLI\Argument(name: 'type', description: 'Content type.')]
  #[CLI\Usage(name: 'drush ac-cac', description: 'Create node alias automatically to content type.')]
  public function contentAliasCreate(string $type) {
    $nodes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $node_types = array_keys($nodes);

    if (!in_array($type, $node_types)) {
      $this->output()->writeln('Content type doesnt exists');
      return;
    }

    // Create the operations array for the batch.
    $operations = [];
    $num_operations = 0;
    $batch_id = 1;

    $query = $this->connection->select('node_field_data', 'd');
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
        'title' => $this->t('Updating @num node aliases', ['@num' => $num_operations]),
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

  /**
   * Convert right_of_occupancy_payment to haso_fee field.
   *
   * @command asu_content:occupancy-to-hasofee
   */
  #[CLI\Command(name: 'asu_content:convertOccupancyToHasofee', aliases: ['ac-oth'])]
  #[CLI\Argument(name: 'project_nid', description: 'Optional argument to fix a specific project')]
  #[CLI\Usage(name: 'drush ac-oth', description: 'Convert right_of_occupancy_payment to haso_fee field.')]
  #[CLI\Usage(name: 'drush ac-oth 1234', description: 'Fix fields in the given project.')]
  public function convertOccupancyToHasofee($project_nid = NULL): void {
    // Specific project nid is given.
    $properties = [
      'type' => 'project',
      'field_ownership_type' => 14,
    ];
    if ($project_nid) {
      $properties['nid'] = $project_nid;
    }
    $projects = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties($properties);

    if (!$projects) {
      return;
    }

    // Create the operations array for the batch.
    $operations = [];
    $num_operations = 0;
    $batch_id = 1;

    $query = $this->connection->select('node', 'n');
    $query->leftJoin('node__field_ownership_type', 'o', 'n.nid = o.entity_id');
    // Single out the given project for the query.
    if ($project_nid) {
      $query->condition('n.nid', $project_nid);
    }
    $query->condition('n.type', 'project');
    $query->condition('o.field_ownership_type_target_id', 14);
    $query->fields('n', ['nid']);
    $results_count = count($projects);

    if ($results_count === 0) {
      return;
    }

    for ($i = 0; $i < $results_count; $i = $i + 10) {
      $query->range($i, 10);
      $results = $query->execute()->fetchAll();

      // Prepare the operation. Here we could do other operations on nodes.
      $this->output()->writeln("Preparing batch: " . $batch_id);

      $operations[] = [
        '\Drupal\asu_content\BatchService::processConvertOccupancyPayment',
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
      'title' => $this->t('Updating @num node aliases', ['@num' => $num_operations]),
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
