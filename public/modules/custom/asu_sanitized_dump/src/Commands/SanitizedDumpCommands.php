<?php

declare(strict_types=1);

namespace Drupal\asu_sanitized_dump\Commands;

use Drupal\asu_sanitized_dump\Config\SanitizerConfig;
use Drupal\asu_sanitized_dump\Dump\SanitizedDumpManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for anonymized production dumps.
 */
final class SanitizedDumpCommands extends DrushCommands {

  /**
   * Constructs the command.
   *
   * @param \Drupal\asu_sanitized_dump\Dump\SanitizedDumpManager $dumpManager
   *   Sanitized dump manager.
   */
  public function __construct(
    private readonly SanitizedDumpManager $dumpManager,
  ) {
    parent::__construct();
  }

  /**
   * Dump the database with PII anonymized; leave live rows unchanged.
   *
   * @param array $options
   *   Command options.
   *
   * @return string
   *   Path to the created dump file.
   */
  #[CLI\Command(name: 'asu:sql:sanitized-dump', aliases: ['asu-sanitized-dump'])]
  #[CLI\Option(name: 'directory', description: 'Directory for sanitized_*.sql (default: system temp directory, e.g. /tmp).')]
  #[CLI\Option(name: 'keep', description: 'Number of newest sanitized dumps to keep after creating (default: 4).')]
  #[CLI\Usage(name: 'drush asu:sql:sanitized-dump', description: 'Write sanitized_YYYYMMDDHHMMSS.sql to the temp directory and prune older dumps.')]
  #[CLI\Usage(name: 'drush asu:sql:sanitized-dump --directory=/app/dumps', description: 'Write the dump under /app/dumps.')]
  public function sanitizedDump(
    array $options = [
      'directory' => '',
      'keep' => '4',
    ],
  ): string {
    $directory = $options['directory'] !== '' && $options['directory'] !== NULL
      ? (string) $options['directory']
      // Drush runs from the Drupal root, which is read-only on the pods.
      : sys_get_temp_dir();
    $keep = max(1, (int) $options['keep']);

    $path = $this->dumpManager->createDump((string) $directory, $keep);
    $this->logger()->success(dt('Sanitized dump saved to !path', ['!path' => $path]));
    $password = SanitizerConfig::usesDefaultPassword()
      ? dt('"!password"', ['!password' => SanitizerConfig::LOCALDEV_PASSWORD])
      : dt('the one hashed in !env', ['!env' => SanitizerConfig::PASSWORD_HASH_ENV]);
    $this->logger()->notice(dt('Live database rows were not modified. Password for imported users is !password.', [
      '!password' => $password,
    ]));
    return $path;
  }

}
