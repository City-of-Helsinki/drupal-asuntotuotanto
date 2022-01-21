<?php

namespace Drupal\asu_api\Exception;

/**
 * Thrown on 5xx error code.
 *
 * Class RequestException.
 *
 * @package Drupal\asu_api\Exception
 */
class ApiException extends \Exception {

  /**
   * Custom string representation of object.
   */
  public function __toString() {
    return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
  }

}
