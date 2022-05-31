<?php

namespace Drupal\asu_migration;

/**
 * Service for uuid creation.
 */
class UuidService {

  /**
   * Create uuid from string.
   *
   * @param string $name_space
   *   Namespace for uuid.
   * @param string $string
   *   String to user for uuid generation.
   *
   * @return string
   *   Uuid
   */
  public function createUuidV5(string $name_space, string $string): string {
    // Getting hexadecimal components of namespace.
    $n_hex = str_replace(['-', '{', '}'], '', $name_space);
    // Binary value string.
    $binray_str = '';
    // Namespace UUID to bits conversion.
    for ($i = 0; $i < strlen($n_hex); $i += 2) {
      $binray_str .= chr(hexdec($n_hex[$i] . $n_hex[$i + 1]));
    }
    // Hash value.
    $hashing = sha1($binray_str . $string);

    return sprintf('%08s-%04s-%04x-%04x-%12s',
      // 32 bits for the time_low
      substr($hashing, 0, 8),
      // 16 bits for the time_mid
      substr($hashing, 8, 4),
      // 16 bits for the time_hi,
      (hexdec(substr($hashing, 12, 4)) & 0x0fff) | 0x5000,
      // 8 bits and 16 bits for the clk_seq_hi_res,
      // 8 bits for the clk_seq_low,
      (hexdec(substr($hashing, 16, 4)) & 0x3fff) | 0x8000,
      // 48 bits for the node
      substr($hashing, 20, 12)
    );
  }

}
