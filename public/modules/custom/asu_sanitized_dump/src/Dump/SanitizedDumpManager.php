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

    $this->retention->prune($directory, $keep);

    $outfile = $directory . '/sanitized_' . date('YmdHis') . '.sql';
    $info = Database::getConnectionInfo('default')['default'];
    if (($info['driver'] ?? '') !== 'mysql') {
      throw new \RuntimeException('Sanitized dumps require a MySQL/MariaDB database.');
    }

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
    $dump->start($outfile);

    if (!is_file($outfile) || filesize($outfile) === 0) {
      throw new \RuntimeException('Unable to create sanitized database dump.');
    }

    return $outfile;
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

}
