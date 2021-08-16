<?php

namespace Drupal\asu_api\Exception;

/**
 * Exception thrown during application process.
 *
 * Class ApplicationRequestException.
 *
 * @package Drupal\asu_api\Exception
 */
class ApplicationRequestException extends \Exception {

  /**
   * Custom string representation of object.
   */
  public function __toString() {
    return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
  }

}
