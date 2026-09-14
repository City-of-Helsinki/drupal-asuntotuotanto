<?php

declare(strict_types=1);

namespace Drupal\asu_application\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control for application payment entities.
 */
final class ApplicationPaymentAccess extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($operation === 'view') {
      return AccessResult::allowedIfHasPermissions(
        $account,
        ['view application payments', 'administer applications'],
        'OR'
      );
    }

    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::forbidden();
  }

}
