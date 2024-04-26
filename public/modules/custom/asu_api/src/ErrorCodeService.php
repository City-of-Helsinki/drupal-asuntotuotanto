<?php

namespace Drupal\asu_api;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Handle error code sent by backend api.
 */
class ErrorCodeService {

  /**
   * Config.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  private $config;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config) {
    $this->config = $config->get('asu_api.error_codes');
  }

  /**
   * Get error message by the error code returned by Backend api.
   *
   * @param string $code
   *   Error code.
   * @param string $languageCode
   *   Language for the error.
   *
   * @return mixed
   *   The message shown for the user.
   */
  public function getErrorMessageByCode(string $code, string $languageCode): ?string {
    return $this->config->get("{$languageCode}.{$code}");
  }

}
