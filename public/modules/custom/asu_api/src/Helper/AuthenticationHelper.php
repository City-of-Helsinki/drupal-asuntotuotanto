<?php

namespace Drupal\asu_api\Helper;

use Drupal\user\UserInterface;

/**
 * Authentication helper.
 */
class AuthenticationHelper {

  /**
   * Check if token is active.
   *
   * @param string $token
   *   Users authentication token.
   *
   * @return bool
   *   Is token still usable.
   */
  public static function isTokenAlive(string $token): bool {
    $token = explode(',', base64_decode($token));
    foreach ($token as $value) {
      if (strpos($value, 'exp') !== FALSE) {
        $int = (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        return strtotime('now') < $int;
      }
    }
    return FALSE;
  }

  /**
   * Tempstore key for a sender's backend authentication token.
   *
   * Tokens must be keyed per backend identity. PrivateTempStore is scoped to
   * the logged-in Drupal user, so a single 'asu_api_token' key caused admins
   * to reuse their own JWT when fetching a customer's profile (HTTP 403).
   *
   * @param \Drupal\user\UserInterface $account
   *   Backend request sender.
   *
   * @return string
   *   Tempstore key.
   */
  public static function getTokenStoreKey(UserInterface $account): string {
    $profileId = '';
    if ($account->hasField('field_backend_profile')) {
      $profileId = (string) ($account->get('field_backend_profile')->value ?? '');
    }
    if ($profileId !== '') {
      return 'asu_api_token:' . $profileId;
    }
    return 'asu_api_token:uid:' . $account->id();
  }

}
