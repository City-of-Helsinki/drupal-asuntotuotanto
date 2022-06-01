<?php

namespace Drupal\asu_migration;

use Drupal\asu_api\Api\BackendApi\BackendApi;

/**
 * Migration base.
 */
abstract class AsuMigrationBase {

  /**
   * File resource.
   *
   * @var resource
   */
  protected $file;

  /**
   * Construct.
   *
   * @param Drupal\asu_migration\UuidService $uuidService
   *   Create uuid from string.
   * @param Drupal\asu_api\Api\BackendApi\BackendApi $backendApi
   *   Send data to backend.
   */
  public function __construct(
    protected UuidService $uuidService,
    protected BackendApi $backendApi,
  ) {
  }

  /**
   * Handle migration.
   */
  abstract public function migrate(): array;

  /**
   * Loop though csv file.
   */
  protected function rows(): iterable {
    while (!feof($this->file)) {
      $row = fgetcsv($this->file, 4096);
      yield $row;
    }
    fclose($this->file);
  }

}
