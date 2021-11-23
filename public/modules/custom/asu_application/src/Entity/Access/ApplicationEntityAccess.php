<?php

declare(strict_types = 1);

namespace Drupal\asu_application\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for application entities.
 */
class ApplicationEntityAccess extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $createPermission = 'create application';
    $administratePermission = 'administer applications';
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIf(
          ($account->id() === $entity->getOwnerId() && $account->hasPermission($createPermission)) ||
          $account->hasPermission($administratePermission)
        );

      case 'update':
        return AccessResult::allowedIf(
          ($account->id() === $entity->getOwnerId() && $account->hasPermission($createPermission)) ||
          $account->hasPermission($administratePermission)
        );
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      'create application',
      'administer applications'
    ]);
  }

}
