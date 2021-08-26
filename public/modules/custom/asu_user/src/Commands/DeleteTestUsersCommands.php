<?php

namespace Drupal\asu_user\Commands;

use Drush\Commands\DrushCommands;
use Drupal\asu_user\DeleteTestUsers;

/**
 * A drush command file.
 *
 * @package Drupal\asu_user\Commands
 */
class DeleteTestUsersCommands extends DrushCommands {

  /**
   * Drush command that deletes all users starting with "test_".
   *
   * @command asu_user:deleteTestUsers
   * @aliases asu:delete-test-users asu:dtu
   * @usage asu_user:deleteTestUsers
   */
  public function deleteTestUsers () {
    /** @var DeleteTestUsers $delete_service */
    $delete_service = \Drupal::service('asu_user.delete_test_users');
    $delete_service->doDeleteTestUsers();
    $this->output()->writeln('Test users have been deleted.');
  }

}