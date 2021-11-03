<?php

namespace Drupal\asu_api\Exception;

/**
 * Exception thrown when sending request.
 *
 * Class RequestException.
 *
 * @package Drupal\asu_api\Exception
 */
class ResponseParameterException extends \Exception {

  /**
   * Custom string representation of object.
   */
  public function __toString() {
    return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
  }

}
