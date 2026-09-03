<?php

declare(strict_types=1);

namespace Drupal\asu_sanitized_dump\Dump;

/**
 * Keeps only the newest sanitized dump files in a directory.
 */
final class DumpRetention {

  /**
   * Delete older sanitized_*.sql files, keeping the newest ones.
   *
   * @param string $directory
   *   Directory that may contain sanitized dumps.
   * @param int $keep
   *   Number of newest files to retain.
   *
   * @return string[]
   *   Paths that were deleted.
   */
  public function prune(string $directory, int $keep = 4): array {
    $pattern = rtrim($directory, '/\\') . '/sanitized_*.sql';
    $files = glob($pattern) ?: [];
    if ($files === []) {
      return [];
    }

    usort($files, static function (string $a, string $b): int {
      return filemtime($b) <=> filemtime($a);
    });

    $deleted = [];
    foreach (array_slice($files, $keep) as $old) {
      if (is_file($old) && unlink($old)) {
        $deleted[] = $old;
      }
    }
    return $deleted;
  }

}
