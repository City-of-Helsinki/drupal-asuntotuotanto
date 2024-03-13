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

    $node_ids = \Drupal::entityQuery('node')
      ->condition('type', $type)
      ->accessCheck(TRUE)
      ->execute();

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($node_ids);

    foreach($nodes as $node) {
      /** @var \Drupal\node\Entity $node */
      $node->path->pathauto = 1;
      $node->save();
    }
  }

}
