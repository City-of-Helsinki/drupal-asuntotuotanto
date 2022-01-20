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
  public function __construct(array $message, int $code = 0, $previous = NULL) {
    $this->apiErrorCode = $message['code'];
    parent::__construct($message['message'], $code, $previous);
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

}
