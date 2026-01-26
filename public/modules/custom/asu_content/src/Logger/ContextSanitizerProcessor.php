<?php

namespace Drupal\asu_content\Logger;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Strips deep context structures before Monolog normalization.
 */
final class ContextSanitizerProcessor implements ProcessorInterface {

  /**
   * Maximum depth we keep for nested context structures.
   */
  private const MAX_DEPTH = 2;

  /**
   * {@inheritdoc}
   */
  public function __invoke(LogRecord $record): LogRecord {
    if (empty($record->context)) {
      return $record;
    }

    return $record->with(context: $this->sanitizeValue($record->context, 0));
  }

  /**
   * Recursively trims deep structures and removes noisy keys.
   */
  private function sanitizeValue(mixed $value, int $depth): mixed {
    if ($depth >= self::MAX_DEPTH) {
      if (is_array($value) || is_object($value)) {
        return '[context trimmed]';
      }
      return $value;
    }

    if (is_array($value)) {
      foreach ($value as $key => $item) {
        if ($key === 'backtrace' || $key === '@backtrace_string') {
          unset($value[$key]);
          continue;
        }
        $value[$key] = $this->sanitizeValue($item, $depth + 1);
      }
    }

    return $value;
  }

}
