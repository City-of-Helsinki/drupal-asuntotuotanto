<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_sanitized_dump\Unit;

use Drupal\asu_sanitized_dump\Dump\DumpRetention;
use Drupal\asu_sanitized_dump\Dump\SanitizedDumpManager;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for writing dumps atomically and rotating old ones.
 *
 * @group asu_sanitized_dump
 *
 * @coversDefaultClass \Drupal\asu_sanitized_dump\Dump\SanitizedDumpManager
 */
final class SanitizedDumpManagerTest extends UnitTestCase {

  /**
   * Temporary directory for dump files.
   *
   * @var string
   */
  private string $directory;

  /**
   * Manager under test.
   *
   * @var \Drupal\asu_sanitized_dump\Dump\SanitizedDumpManager
   */
  private SanitizedDumpManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->directory = sys_get_temp_dir() . '/asu_sanitized_dump_test_' . uniqid('', TRUE);
    mkdir($this->directory);
    $this->manager = new SanitizedDumpManager(new DumpRetention());

    for ($i = 1; $i <= 4; $i++) {
      file_put_contents($this->directory . "/sanitized_2026010{$i}120000.sql", "old-$i");
    }
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
   * A successful dump is renamed into place and rotation keeps `keep` files.
   *
   * - Four old dumps exist
   * - writeAndRotate(keep=4) writes a new dump
   * - Exactly four dumps remain: the new one and the three newest old ones
   * - No .tmp file is left behind.
   *
   * @covers ::writeAndRotate
   */
  public function testSuccessfulDumpRotatesToKeep(): void {
    $path = $this->manager->writeAndRotate(
      $this->directory,
      4,
      static fn (string $tmp) => file_put_contents($tmp, 'new dump'),
    );

    $this->assertFileExists($path);
    $this->assertSame('new dump', file_get_contents($path));
    $this->assertCount(4, glob($this->directory . '/sanitized_*.sql'));
    $this->assertFileDoesNotExist($this->directory . '/sanitized_20260101120000.sql');
    $this->assertSame([], glob($this->directory . '/*.tmp'));
  }

  /**
   * A failing dump leaves existing dumps untouched and cleans up.
   *
   * - The writer writes partial output, then throws
   * - The exception propagates
   * - All four old dumps remain and no .tmp file is left behind.
   *
   * @covers ::writeAndRotate
   */
  public function testFailedDumpKeepsExistingDumps(): void {
    try {
      $this->manager->writeAndRotate(
        $this->directory,
        4,
        static function (string $tmp): void {
          file_put_contents($tmp, 'partial');
          throw new \RuntimeException('connection lost');
        },
      );
      $this->fail('Expected the dump failure to propagate.');
    }
    catch (\RuntimeException $e) {
      $this->assertSame('connection lost', $e->getMessage());
    }

    $this->assertCount(4, glob($this->directory . '/sanitized_*.sql'));
    $this->assertSame([], glob($this->directory . '/*.tmp'));
  }

  /**
   * An empty dump is treated as a failure.
   *
   * - The writer creates an empty file
   * - A RuntimeException is thrown
   * - All four old dumps remain and no .tmp file is left behind.
   *
   * @covers ::writeAndRotate
   */
  public function testEmptyDumpFails(): void {
    $this->expectException(\RuntimeException::class);

    try {
      $this->manager->writeAndRotate(
        $this->directory,
        4,
        static fn (string $tmp) => touch($tmp),
      );
    }
    finally {
      $this->assertCount(4, glob($this->directory . '/sanitized_*.sql'));
      $this->assertSame([], glob($this->directory . '/*.tmp'));
    }
  }

}
