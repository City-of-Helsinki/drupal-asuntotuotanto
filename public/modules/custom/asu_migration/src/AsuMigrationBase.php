<?php

namespace Drupal\asu_migration;

use Drupal\asu_api\Api\BackendApi\BackendApi;

/**
 *
 */
abstract class AsuMigrationBase {

  protected $file;

  /**
   *
   */
  public function __construct(
    protected UuidService $uuidService,
    protected BackendApi $backendApi,
  ) {
  }

  /**
   *
   */
  abstract public function migrate(): array;

  /**
   *
   */
  protected function rows(): iterable {
    while (!feof($this->file)) {
      $row = fgetcsv($this->file, 4096);
      yield $row;
    }
    fclose($this->file);
    return;
  }

}
