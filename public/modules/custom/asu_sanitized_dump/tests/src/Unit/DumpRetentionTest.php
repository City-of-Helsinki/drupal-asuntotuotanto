<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_sanitized_dump\Unit;

use Drupal\asu_sanitized_dump\Dump\DumpRetention;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for sanitized dump file retention.
 *
 * @group asu_sanitized_dump
 *
 * @coversDefaultClass \Drupal\asu_sanitized_dump\Dump\DumpRetention
 */
final class DumpRetentionTest extends UnitTestCase {

  /**
   * Temporary directory for dump files.
   *
   * @var string
   */
  private string $directory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->directory = sys_get_temp_dir() . '/asu_sanitized_dump_test_' . uniqid('', TRUE);
    mkdir($this->directory);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach (glob($this->directory . '/*') ?: [] as $file) {
      @unlink($file);
    }
    @rmdir($this->directory);
    parent::tearDown();
  }

  /**
   * Prune keeps the four newest dumps and deletes older ones.
   *
   * - Five drupal_sanitized_*.sql files exist with increasing mtimes
   * - After prune(keep=4), the oldest file is removed
   * - The four newest paths remain.
   */
  public function testPruneKeepsFourNewest(): void {
    $created = [];
    for ($i = 1; $i <= 5; $i++) {
      $path = $this->directory . "/drupal_sanitized_2026010{$i}120000.sql";
      file_put_contents($path, "dump-$i");
      touch($path, 1_700_000_000 + $i);
      clearstatcache(TRUE, $path);
      $created[] = $path;
    }

    $deleted = (new DumpRetention())->prune($this->directory, 4);

    $this->assertSame([$created[0]], $deleted);
    $this->assertFileDoesNotExist($created[0]);
    foreach (array_slice($created, 1) as $path) {
      $this->assertFileExists($path);
    }
  }

  /**
   * Prune orders dumps by the timestamp in their name, not by mtime.
   *
   * - Five dumps exist; the oldest by name has the newest mtime (e.g. copied)
   * - prune(keep=4) still deletes the oldest by name.
   */
  public function testPruneOrdersByNameNotMtime(): void {
    $created = [];
    for ($i = 1; $i <= 5; $i++) {
      $path = $this->directory . "/drupal_sanitized_2026010{$i}120000.sql";
      file_put_contents($path, "dump-$i");
      touch($path, 1_700_000_000 - $i);
      clearstatcache(TRUE, $path);
      $created[] = $path;
    }

    $deleted = (new DumpRetention())->prune($this->directory, 4);

    $this->assertSame([$created[0]], $deleted);
  }

  /**
   * Prune only touches Drupal dumps.
   *
   * - Five Drupal dumps and older backend (django_) dumps share a directory
   * - prune(keep=4) deletes only the oldest Drupal dump.
   */
  public function testPruneLeavesBackendDumps(): void {
    for ($i = 1; $i <= 5; $i++) {
      file_put_contents($this->directory . "/drupal_sanitized_2026020{$i}120000.sql", "drupal-$i");
    }
    $backend = $this->directory . '/django_sanitized_20260101120000.sql';
    file_put_contents($backend, 'django');

    $deleted = (new DumpRetention())->prune($this->directory, 4);

    $this->assertSame([$this->directory . '/drupal_sanitized_20260201120000.sql'], $deleted);
    $this->assertFileExists($backend);
  }

  /**
   * Prune is a no-op when fewer than keep files exist.
   *
   * - Two dump files exist
   * - prune(keep=4) deletes nothing.
   */
  public function testPruneNoOpWhenUnderLimit(): void {
    file_put_contents($this->directory . '/drupal_sanitized_20260101120000.sql', 'a');
    file_put_contents($this->directory . '/drupal_sanitized_20260102120000.sql', 'b');

    $deleted = (new DumpRetention())->prune($this->directory, 4);

    $this->assertSame([], $deleted);
  }

}
