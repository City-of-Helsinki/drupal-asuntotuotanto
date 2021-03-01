<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ahjo\Traits;

use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;

/**
 * Trait for shared migration tasks.
 */
trait MigrateTrait {

  use ApiTestTrait;

  /**
   * The issue migration.
   */
  protected function createIssueMigration() : void {
    $responses = [
      new Response(200, [], json_encode([
        'meta' => [
          'limit' => 20,
          'offset' => 0,
          'total_count' => 40,
        ],
        'objects' => [],
      ])),
    ];

    $id = 1;
    for ($page = 0; $page < 2; $page++) {
      $response = [
        'meta' => [
          'limit' => 20,
          'offset' => $page * 20,
          'total_count' => 40,
        ],
        'objects' => [],
      ];

      for ($i = 1; $i <= 20; $i++) {
        $response['objects'][] = [
          'id' => $id,
          'subject' => 'Name ' . $id,
        ];
        $id++;
      }
      $responses[] = new Response(200, [], json_encode($response));
    }

    $this->container->set('http_client', $this->createMockHttpClient($responses));
    $this->executeMigration('ahjo_issues');
  }

}
