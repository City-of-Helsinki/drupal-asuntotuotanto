<?php

declare(strict_types=1);

namespace Drupal\asu_apartment_search\Asset;

/**
 * Builds a Drupal asset library that loads the app from Vite during development.
 */
final class DevLibraryBuilder {

  /**
   * Replaces production files with Vite's development entry points.
   *
   * @param array $library
   *   Existing Drupal library definition.
   * @param string $origin
   *   Vite server origin, e.g. https://localhost:5173.
   *
   * @return array
   *   Updated library definition.
   */
  public static function build(array $library, string $origin): array {
    $parts = parse_url($origin);
    if (
      $parts === FALSE ||
      !isset($parts['scheme'], $parts['host']) ||
      !in_array($parts['scheme'], ['http', 'https'], TRUE) ||
      isset($parts['user']) ||
      isset($parts['pass']) ||
      isset($parts['query']) ||
      isset($parts['fragment']) ||
      (isset($parts['path']) && $parts['path'] !== '')
    ) {
      throw new \InvalidArgumentException('The Vite development origin must be an HTTP(S) origin without a path.');
    }

    $origin = rtrim($origin, '/');
    $module_script = [
      'type' => 'external',
      'attributes' => ['type' => 'module'],
    ];

    $library['js'] = [
      $origin . '/@vite/client' => $module_script,
      $origin . '/src/main.tsx' => $module_script,
    ];
    $library['css'] = [];

    return $library;
  }

}
