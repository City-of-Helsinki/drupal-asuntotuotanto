<?php

namespace Drupal\asu_api\Api\BackendApi\Helper;

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
    foreach ($token as $key => $value) {
      if (strpos($value, 'exp') !== FALSE) {
        $int = (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        return strtotime('now') < $int;
      }
    }
    return FALSE;
  }

}
