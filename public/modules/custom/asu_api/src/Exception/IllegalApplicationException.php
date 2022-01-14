<?php

namespace Drupal\asu_api\Exception;

/**
 * Exception thrown when response is 4xx.
 *
 * Class RequestException.
 *
 * @package Drupal\asu_api\Exception
 */
class IllegalApplicationException extends \Exception {

  /**
   * Error code mentioned in the response message.
   *
   * @var string
   */
  protected string $apiErrorCode = '';

  /**
   * Constructor.
   */
  public function __construct($message = "", int $code = 0, $previous = NULL) {
    if (is_array($message) && $value = reset($message)) {
      $message = $value;
    }
    if ($message && $apiErrorCode = $this->parseApiErrorCode($message)) {
      $this->apiErrorCode = $apiErrorCode;
    }
    parent::__construct($message, $code, $previous);
  }

  /**
   * Return api error code.
   */
  public function getApiErrorCode(): string {
    return $this->apiErrorCode;
  }

  /**
   * Custom string representation of object.
   */
  public function __toString() {
    return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
  }

  /**
   * Parse api error code from the beginning of the message.
   *
   * @param string $message
   *   Response message.
   *
   * @return string
   *   Error code.
   */
  private function parseApiErrorCode(string $message): string {
    $matches = [];
    preg_match('(\d+)', $message, $matches);
    if (!isset($matches[0])) {
      return FALSE;
    }
    if (strpos($message, $matches[0]) === 0) {
      return (string) $matches[0];
    }
    return '';
  }

}
