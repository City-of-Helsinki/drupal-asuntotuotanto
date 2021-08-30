<?php

namespace Drupal\asu_user;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class DeleteTestUsers.
 */
class DeleteTestUsers {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an object.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public function doDeleteTestUsers() {
    $user_ids = \Drupal::entityQuery('user')->execute();
    $users = \Drupal\user\Entity\User::loadMultiple($user_ids);

    foreach ($users as $user) {
      if (str_starts_with($user->getAccountName(), 'test_') || str_starts_with($user->getEmail(), 'test_')) {
        $user->delete();
      }
    }
  }

}
