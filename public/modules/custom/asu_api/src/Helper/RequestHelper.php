<?php

namespace Drupal\asu_api\Helper;

use GuzzleHttp\Exception\ClientException;

/**
 * Helper functions for asu_api requests.
 */
class RequestHelper {

  /**
   * Parse custom error code sent by API.
   *
   * @param \GuzzleHttp\Exception\ClientException $e
   *   Exception thrown by guzzle which contains the message & error code.
   *
   * @return array
   *   Array with error message and code.
   */
  public static function parseErrorCode(ClientException $e): array {
    $messages = json_decode((string) $e->getResponse()->getBody()->getContents(), TRUE);
    if (is_array($messages) && count($messages) != count($messages, COUNT_RECURSIVE)) {
      $result = array_reduce($messages, 'array_merge', []);
      $message = $result[0];
    }
    else {
      $message = $messages;
    }
    return $message;
  }

}
