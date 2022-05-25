<?php

namespace Drupal\asu_migration\Commands;

use Drupal\asu_migration\UserMigrationService;
use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 */
class MigrateUserCsvCommand extends DrushCommands {

  /**
   * Construct.
   */
  public function __construct(private UserMigrationService $migration) {
    parent::__construct();
  }

  /**
   * Drush command that migrates users from file.
   *
   * @command asu:user-migration
   */
  public function migrateUsers() {
    $errors = $this->migration->migrate();
    foreach ($errors as $key => $error) {
      $this->output()->writeln("$key: $error");
    }
  }

}
