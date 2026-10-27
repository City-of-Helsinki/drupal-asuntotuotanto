<?php

declare(strict_types=1);

namespace Drupal\asu_sanitized_dump\Commands;

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
  #[CLI\Option(name: 'directory', description: 'Directory for sanitized_*.sql (default: current working directory).')]
  #[CLI\Option(name: 'keep', description: 'Number of newest sanitized dumps to keep after creating (default: 4).')]
  #[CLI\Usage(name: 'drush asu:sql:sanitized-dump', description: 'Write sanitized_YYYYMMDDHHMMSS.sql and prune older dumps.')]
  #[CLI\Usage(name: 'drush asu:sql:sanitized-dump --directory=/tmp', description: 'Write the dump under /tmp.')]
  public function sanitizedDump(
    array $options = [
      'directory' => '',
      'keep' => '4',
    ],
  ): string {
    $directory = $options['directory'] !== '' && $options['directory'] !== NULL
      ? (string) $options['directory']
      : getcwd();
    $keep = max(1, (int) $options['keep']);

    $path = $this->dumpManager->createDump((string) $directory, $keep);
    $this->logger()->success(dt('Sanitized dump saved to !path', ['!path' => $path]));
    $this->logger()->notice(dt('Live database rows were not modified. Local password for imported users is "localdev".'));
    return $path;
  }

}
