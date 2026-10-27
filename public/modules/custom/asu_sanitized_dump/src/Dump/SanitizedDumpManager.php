<?php

declare(strict_types=1);

namespace Drupal\asu_sanitized_dump\Dump;

use Drupal\asu_sanitized_dump\Config\SanitizerConfig;
use Drupal\Core\Database\Database;
use druidfi\GdprDump\MysqldumpGdpr;

/**
 * Creates anonymized SQL dumps without mutating the live database.
 */
final class SanitizedDumpManager {

  /**
   * Constructs the dump manager.
   *
   * @param \Drupal\asu_sanitized_dump\Dump\DumpRetention $retention
   *   Retention helper for old dump files.
   */
  public function __construct(
    private readonly DumpRetention $retention,
  ) {}

  /**
   * Create a sanitized dump file and prune older dumps in the directory.
   *
   * Uses the PHP mysqldump implementation so SQL expressions with quotes are
   * not broken by shell escaping. Live rows are only SELECTed.
   *
   * @param string $directory
   *   Target directory (must be writable).
   * @param int $keep
   *   Number of newest sanitized_*.sql files to keep after creating.
   *
   * @return string
   *   Absolute path of the created dump file.
   *
   * @throws \Exception
   *   When the dump command fails.
   */
  public function createDump(string $directory, int $keep = 4): string {
    $directory = rtrim($directory, '/\\');
    if (!is_dir($directory) && !mkdir($directory, 0775, TRUE) && !is_dir($directory)) {
      throw new \RuntimeException(sprintf('Unable to create dump directory %s.', $directory));
    }

    $info = Database::getConnectionInfo('default')['default'];
    if (($info['driver'] ?? '') !== 'mysql') {
      throw new \RuntimeException('Sanitized dumps require a MySQL/MariaDB database.');
    }

    $this->assertCustomTablesClassified();

    return $this->writeAndRotate(
      $directory,
      $keep,
      fn (string $path) => $this->dumpDatabase($info, $path),
    );
  }

  /**
   * Write a dump via a .tmp file, then prune older dumps.
   *
   * A failed or empty dump is removed and existing dumps are left untouched.
   *
   * @param string $directory
   *   Existing target directory.
   * @param int $keep
   *   Number of newest sanitized_*.sql files to keep after creating.
   * @param callable $write
   *   Writes the dump to the path it is given.
   *
   * @return string
   *   Path of the created dump file.
   *
   * @throws \Throwable
   *   When writing fails or produces an empty file.
   */
  public function writeAndRotate(string $directory, int $keep, callable $write): string {
    $outfile = $directory . '/sanitized_' . date('YmdHis') . '.sql';
    $tmpfile = $outfile . '.tmp';

    try {
      $write($tmpfile);
      clearstatcache(TRUE, $tmpfile);
      if (!is_file($tmpfile) || filesize($tmpfile) === 0) {
        throw new \RuntimeException('Unable to create sanitized database dump.');
      }
      if (!rename($tmpfile, $outfile)) {
        throw new \RuntimeException(sprintf('Unable to move dump to %s.', $outfile));
      }
    }
    catch (\Throwable $e) {
      if (is_file($tmpfile)) {
        unlink($tmpfile);
      }
      throw $e;
    }

    $this->retention->prune($directory, $keep);
    return $outfile;
  }

  /**
   * Dump the database to a file with ASU PII expressions applied.
   *
   * @param array $info
   *   Database connection info for the default connection.
   * @param string $path
   *   Destination file.
   */
  private function dumpDatabase(array $info, string $path): void {
    $host = $info['host'] ?? '127.0.0.1';
    $port = $info['port'] ?? '3306';
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', $host, $port, $info['database']);

    $dump = new MysqldumpGdpr(
      $dsn,
      $info['username'] ?? '',
      $info['password'] ?? '',
      [
        'add-drop-table' => TRUE,
        'single-transaction' => TRUE,
        'no-data' => $this->resolveStructureTables(),
        'gdpr-expressions' => SanitizerConfig::expressions(),
        'gdpr-replacements' => SanitizerConfig::replacements(),
      ]
    );
    $dump->start($path);
  }

  /**
   * Expand structure-table patterns to concrete table names that exist.
   *
   * @return string[]
   *   Table names whose data should be omitted from the dump.
   */
  private function resolveStructureTables(): array {
    $connection = Database::getConnection();
    $prefix = $connection->getPrefix();
    $existing = $connection->schema()->findTables('%');
    $matched = [];

    foreach (SanitizerConfig::structureTables() as $pattern) {
      $regex = '/^' . str_replace(['\\*', '%'], ['.*', '.*'], preg_quote($pattern, '/')) . '$/';
      foreach ($existing as $unprefixed) {
        if (preg_match($regex, $unprefixed)) {
          // Mysqldump sees physical table names (with Drupal prefix if any).
          $matched[] = $prefix . $unprefixed;
        }
      }
    }

    return array_values(array_unique($matched));
  }

  /**
   * Refuse to dump when a custom PII table has an unclassified column.
   *
   * Custom tables are created by update hooks, outside entity storage, so the
   * coverage test cannot see columns added later. Checking the live schema
   * makes such a column fail the dump instead of leaking.
   *
   * @throws \RuntimeException
   *   When a column has neither an expression nor a safe listing.
   */
  private function assertCustomTablesClassified(): void {
    $connection = Database::getConnection();
    $table_columns = [];
    foreach (array_keys(SanitizerConfig::CUSTOM_TABLES) as $table) {
      if ($connection->schema()->tableExists($table)) {
        $table_columns[$table] = $connection->query('SHOW COLUMNS FROM {' . $table . '}')->fetchCol();
      }
    }

    $unclassified = SanitizerConfig::unclassifiedCustomColumns($table_columns);
    if ($unclassified !== []) {
      throw new \RuntimeException(sprintf(
        'Refusing to dump: unclassified columns %s. Add an expression or a safe listing in SanitizerConfig.',
        implode(', ', $unclassified),
      ));
    }
  }

}
