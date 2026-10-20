<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_apartment_search\Unit;

use Drupal\asu_apartment_search\Asset\DevLibraryBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Vite development library definition.
 *
 * @group asu_apartment_search
 *
 * @coversDefaultClass \Drupal\asu_apartment_search\Asset\DevLibraryBuilder
 */
final class DevLibraryBuilderTest extends UnitTestCase {

  /**
   * Development library points at Vite and drops production assets.
   */
  public function testBuildsModuleScriptsForVite(): void {
    $library = [
      'js' => ['assets/react/hitas/asu_react_main.js' => []],
      'css' => ['theme' => ['assets/react/hitas/asu_react_main.css' => []]],
      'version' => 'production-hash',
    ];

    $result = DevLibraryBuilder::build($library, 'https://localhost:5173');

    $this->assertSame([
      'https://localhost:5173/@vite/client' => [
        'type' => 'external',
        'attributes' => ['type' => 'module'],
      ],
      'https://localhost:5173/src/main.tsx' => [
        'type' => 'external',
        'attributes' => ['type' => 'module'],
      ],
    ], $result['js']);
    $this->assertSame([], $result['css']);
    $this->assertSame('production-hash', $result['version']);
  }

  /**
   * Development server origin must not permit script injection via a path.
   */
  public function testRejectsInvalidOrigin(): void {
    $this->expectException(\InvalidArgumentException::class);
    DevLibraryBuilder::build([], 'javascript:alert(1)');
  }

}
