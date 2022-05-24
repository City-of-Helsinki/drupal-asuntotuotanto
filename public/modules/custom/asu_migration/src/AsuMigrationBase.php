<?php

namespace Drupal\asu_migration;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_migration\UuidService;

abstract class AsuMigrationBase {

  protected $file;

  public function __construct(
    protected UuidService $uuidService,
    protected BackendApi $backendApi,
  )
  {
  }

  public abstract function migrate(): array;

  protected function rows(): iterable {
    while (!feof($this->file)) {
      $row = fgetcsv($this->file, 4096);
      yield $row;
    }
    fclose($this->file);
    return;
  }

}
