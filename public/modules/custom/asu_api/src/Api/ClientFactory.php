<?php

namespace Drupal\asu_api\Api;

use GuzzleHttp\Client;

class ClientFactory {
  public static function create(string $baseUrl, array $options = []): Client {
    $options['Content-Type'] = 'application/json';
    $options['base_url'] = $baseUrl;
    return new Client($options);
  }
}
