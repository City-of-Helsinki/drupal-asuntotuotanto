<?php

declare(strict_types=1);

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
    switch ($operation) {
      case 'view':
        return $this->viewOperationHandling($entity, $account);

      case 'update':
        return $this->updateOperationAccess($entity, $account);
    }
    return parent::checkAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions(
      $account,
      ['create application', 'administer applications'],
      'OR'
    );
  }

  /**
   * {@inheritdoc}
   */
  private function viewOperationHandling(EntityInterface $entity, AccountInterface $account): AccessResult {
    $createPermission = 'view application';
    $administratePermission = 'administer applications';
    return AccessResult::allowedIf(
      ($account->id() === $entity->getOwnerId() && $account->hasPermission($createPermission)) ||
      $account->hasPermission($administratePermission)
    );
  }

  /**
   * {@inheritdoc}
   */
  private function updateOperationAccess(EntityInterface $entity, AccountInterface $account): AccessResult {
    $createPermission = 'create application';
    $administratePermission = 'administer applications';
    return AccessResult::allowedIf(
      ($account->id() === $entity->getOwnerId() && $account->hasPermission($createPermission)) ||
      $account->hasPermission($administratePermission)
    );
  }

}
