<?php

namespace Drupal\asu_migration;

use Drupal\asu_api\Api\BackendApi\BackendApi;

/**
 *
 */
abstract class AsuMigrationBase {

  protected $file;

  /**
   * Construct.
   *
   * @param UuidService $uuidService
   *   Create uuid from string.
   * @param BackendApi $backendApi
   *   Send data to backend.
   *
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
