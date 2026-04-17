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
      case 'delete':
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

    $isOwner = $account->id() === $entity->getOwnerId();
    $isCoApplicant = $this->isUserCoApplicant($entity, $account);

    return AccessResult::allowedIf(
      (($isOwner || $isCoApplicant) && $account->hasPermission($createPermission)) ||
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

  /**
   * Check whether current account is mapped as co-applicant for the entity.
   */
  private function isUserCoApplicant(EntityInterface $entity, AccountInterface $account): bool {
    if (!$account->isAuthenticated()) {
      return FALSE;
    }

    $schema = \Drupal::database()->schema();
    if (!$schema->tableExists('asu_application_co_applicant_map')) {
      return FALSE;
    }

    $accountEntity = \Drupal::entityTypeManager()->getStorage('user')->load($account->id());
    if (!$accountEntity || !$accountEntity->hasField('field_saml_hash')) {
      return FALSE;
    }

    $samlHash = $accountEntity->get('field_saml_hash')->value;
    if (empty($samlHash)) {
      return FALSE;
    }

    $exists = \Drupal::database()
      ->select('asu_application_co_applicant_map', 'm')
      ->fields('m', ['application_id'])
      ->condition('application_id', (int) $entity->id())
      ->condition('co_applicant_saml_hash', $samlHash)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return (bool) $exists;
  }

}
