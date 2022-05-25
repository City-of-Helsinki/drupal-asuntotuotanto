<?php

namespace Drupal\asu_migration\Commands;

use Drupal\asu_migration\ProjectMigrationService;
use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 */
class MigrateProjectCsvCommand extends DrushCommands {

  /**
   * Construct.
   */
  public function __construct(private ProjectMigrationService $migration) {
    parent::__construct();
  }

  /**
   * Drush command that migrates projects from file.
   *
   * @command asu:project-migration
   */
  public function migrateProjects() {
    $errors = $this->migration->migrate();
    foreach ($errors as $key => $error) {
      $this->output()->writeln("$key: $error");
    }
  }

}
