<?php

namespace Drupal\asu_api\Api;

use GuzzleHttp\Client;

/**
 * Configure http client.
 */
class ClientFactory {

  /**
   * Configure http client.
   *
   * @param string $baseUrlVariable
   *   Base url variable.
   * @param array $options
   *   Client options.
   *
   * @return GuzzleHttp\Client
   *   Http client.
   */
  public static function create(string $baseUrlVariable, array $options = []): Client {
    $options['headers']['Content-Type'] = 'application/json';
    $options['base_url'] = getenv($baseUrlVariable);
    $options['timeout'] = 5;
    return new Client($options);
  }

}
