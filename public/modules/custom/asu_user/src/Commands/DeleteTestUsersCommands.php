<?php

namespace Drupal\asu_user\Commands;

use Drupal\asu_user\DeleteTestUsers;
use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 *
 * @package Drupal\asu_user\Commands
 */
class DeleteTestUsersCommands extends DrushCommands {
  /**
   * The entity type manager.
   *
   * @var \Drupal\asu_user\DeleteTestUsers
   */
  protected $deleteService;

  /**
   * Constructor.
   */
  public function __construct(DeleteTestUsers $delete_service) {
    $this->deleteService = $delete_service;
  }

  /**
   * Drush command that deletes all users starting with "test_".
   *
   * @command asu_user:deleteTestUsers
   * @aliases asu:delete-test-users asu:dtu
   * @usage asu_user:deleteTestUsers
   */
  public function deleteTestUsers() {
    $this->deleteService->doDeleteTestUsers();
    $this->output()->writeln('Test users have been deleted.');
  }

}
