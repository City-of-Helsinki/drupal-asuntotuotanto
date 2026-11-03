<?php

namespace Drupal\asu_application\Util;

/**
 * Adds a [TEST] label to offer notification mail outside production.
 */
final class OfferMailTestLabel {

  private const PREFIX = '[TEST] ';

  private const PRODUCTION_ENVIRONMENTS = ['prod', 'production'];

  /**
   * Whether the current APP_ENV is production.
   */
  public static function isProductionEnvironment(): bool {
    $env = strtolower(trim((string) getenv('APP_ENV')));
    return in_array($env, self::PRODUCTION_ENVIRONMENTS, TRUE);
  }

  /**
   * Prefix email subject when not in production.
   */
  public static function prefixSubject(string $subject): string {
    return self::shouldPrefix() ? self::PREFIX . $subject : $subject;
  }

  /**
   * Prefix email body when not in production.
   */
  public static function prefixBody(string $body): string {
    return self::shouldPrefix() ? self::PREFIX . $body : $body;
  }

  /**
   * Whether offer notification mail should include the [TEST] prefix.
   */
  private static function shouldPrefix(): bool {
    return !self::isProductionEnvironment();
  }

}
