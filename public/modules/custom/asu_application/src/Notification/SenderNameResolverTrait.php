<?php

declare(strict_types=1);

namespace Drupal\asu_application\Notification;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\UserRequest;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserInterface;

/**
 * Shared sender-name resolver for email notifications.
 */
trait SenderNameResolverTrait {

  /**
   * Resolves sender name with backend profile fallback.
   */
  private function resolveNotificationSenderName(
    AccountProxyInterface $currentUser,
    BackendApi $backendApi,
    EntityTypeManagerInterface $entityTypeManager,
    string $fallbackLabel,
  ): string {
    if ($currentUser->isAuthenticated()) {
      $user = $entityTypeManager->getStorage('user')->load((int) $currentUser->id());
      if ($user instanceof UserInterface) {
        $fullName = $this->buildFullName(
          $user->hasField('first_name') && !$user->get('first_name')->isEmpty() ? (string) $user->get('first_name')->value : '',
          $user->hasField('last_name') && !$user->get('last_name')->isEmpty() ? (string) $user->get('last_name')->value : '',
        );

        if ($fullName !== '') {
          return $fullName;
        }

        try {
          if ($user->hasField('field_backend_profile') && !$user->get('field_backend_profile')->isEmpty()) {
            $request = new UserRequest($user);
            $request->setSender($user);
            $response = $backendApi->send($request);
            $userInformation = $response->getUserInformation();

            $backendFullName = $this->buildFullName(
              (string) ($userInformation['first_name'] ?? ''),
              (string) ($userInformation['last_name'] ?? ''),
            );
            if ($backendFullName !== '') {
              return $backendFullName;
            }
          }
        }
        catch (\Exception $e) {
          // Fallback continues below.
        }

        if ($user->hasField('field_full_name') && !$user->get('field_full_name')->isEmpty()) {
          $storedFullName = trim((string) $user->get('field_full_name')->value);
          if ($storedFullName !== '') {
            return $storedFullName;
          }
        }

        if ($user->getDisplayName() !== '') {
          return $user->getDisplayName();
        }
      }
    }

    return $currentUser->getDisplayName() ?: $fallbackLabel;
  }

  /**
   * Builds a normalized full name from first and last name parts.
   */
  private function buildFullName(string $firstName, string $lastName): string {
    return trim(trim($firstName) . ' ' . trim($lastName));
  }

}
