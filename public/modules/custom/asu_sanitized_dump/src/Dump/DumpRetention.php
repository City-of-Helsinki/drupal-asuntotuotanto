<?php

declare(strict_types=1);

namespace Drupal\asu_sanitized_dump\Dump;

/**
 * Keeps only the newest sanitized dump files in a directory.
 */
final class DumpRetention {

  /**
   * Filename prefix of Drupal dumps; the backend's dumps use "django_".
   */
  public const FILE_PREFIX = 'drupal_sanitized_';

  /**
   * Delete older drupal_sanitized_*.sql files, keeping the newest ones.
   *
   * @param string $directory
   *   Directory that may contain sanitized dumps.
   * @param int $keep
   *   Number of newest files to retain.
   *
   * @return string[]
   *   Paths that were deleted.
   */
  public function prune(string $directory, int $keep = 5): array {
    $pattern = rtrim($directory, '/\\') . '/' . self::FILE_PREFIX . '*.sql';
    $files = glob($pattern) ?: [];
    if ($files === []) {
      return [];
    }

    // Names embed a fixed-width timestamp, so they sort chronologically even
    // when copying the files has changed their mtimes.
    rsort($files, SORT_STRING);

    $deleted = [];
    foreach (array_slice($files, $keep) as $old) {
      if (is_file($old) && unlink($old)) {
        $deleted[] = $old;
      }
    }
    return $deleted;
  }

}
