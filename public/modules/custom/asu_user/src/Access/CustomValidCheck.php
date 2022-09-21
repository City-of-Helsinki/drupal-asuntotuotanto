<?php

namespace Drupal\asu_user\Access;

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\user\Entity\User;

/**
 * Checks access for displaying configuration translation page.
 */
class CustomValidCheck implements AccessInterface {

  /**
   * Checks if user has access to login.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    $account = User::load($account->id());

    if ($account->hasField('field_email_is_valid')
      && !$account->get('field_email_is_valid')->isEmpty()) {
      // Get is valid value.
      $is_valid = (int) $account->get('field_email_is_valid')->getValue()[0]['value'];

      // If user has already authenticated.
      // Do not give access to continue.
      if ($is_valid == 1) {
        return AccessResult::forbidden();
      }
    }

    // Default allowed access.
    return AccessResult::allowed();
  }

}
