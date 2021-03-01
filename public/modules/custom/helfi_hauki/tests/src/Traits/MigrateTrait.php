<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_hauki\Traits;

use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Psr7\Response;

/**
 * Trait for shared migration tasks.
 */
trait MigrateTrait {

  use ApiTestTrait;

  /**
   * The resource migration.
   */
  protected function createResourceMigration() : void {
    $responses = [
      new Response(200, [], json_encode(['count' => 20, 'results' => []])),
    ];

    $id = 1;
    for ($page = 1; $page <= 2; $page++) {
      $response = [
        'count' => 20,
        'results' => [],
      ];

      for ($i = 1; $i <= 10; $i++) {
        $response['results'][] = [
          'id' => $id,
          'name' => [
            'fi' => 'Name fi ' . $id,
            'en' => 'Name en ' . $id,
            'sv' => 'Name sv ' . $id,
          ],
          'origins' => [
            [
              'data_source' => [
                'id' => 'tprek',
              ],
              'origin_id' => 'miscinfo-' . $id * 10,
            ],
          ],
        ];
        $id++;
      }
      $responses[] = new Response(200, [], json_encode($response));
    }

    $this->container->set('http_client', $this->createMockHttpClient($responses));
    $this->executeMigration('hauki_resource', [
      'source' => [
        'url' => 'https://hauki-test.oc.hel.ninja/v1/resource/?origin_id_exists=true&page_size=10',
      ],
    ]);
  }

}
