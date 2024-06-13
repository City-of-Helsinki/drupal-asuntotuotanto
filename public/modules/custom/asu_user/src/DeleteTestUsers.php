<?php

namespace Drupal\asu_user;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Delete users with test_ prefix.
 */
class DeleteTestUsers {
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Execute user deletion.
   */
  public function doDeleteTestUsers() {
    $user_ids = $this->entityTypeManager->getStorage('user')->accessCheck(TRUE)->execute();
    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($user_ids);

    foreach ($users as $user) {
      if (str_starts_with($user->getAccountName(), 'test_') || str_starts_with($user->getEmail(), 'test_')) {
        $user->delete();
      }
    }
  }

}
