<?php

namespace Drupal\Tests\asu_application\Unit;

use Drupal\asu_application\Util\OfferMailTestLabel;
use Drupal\Tests\UnitTestCase;

/**
 * Tests offer notification mail [TEST] prefix for non-production environments.
 *
 * @group asu_application
 */
final class OfferMailTestLabelTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    putenv('APP_ENV');
    parent::tearDown();
  }

  /**
   * Production environments are prod and production.
   *
   * @dataProvider productionEnvironmentProvider
   */
  public function testIsProductionEnvironment(string $appEnv): void {
    putenv('APP_ENV=' . $appEnv);
    $this->assertTrue(OfferMailTestLabel::isProductionEnvironment());
  }

  /**
   * Non-production environments receive the [TEST] prefix.
   *
   * @dataProvider nonProductionEnvironmentProvider
   */
  public function testIsNotProductionEnvironment(string $appEnv): void {
    putenv('APP_ENV=' . $appEnv);
    $this->assertFalse(OfferMailTestLabel::isProductionEnvironment());
  }

  /**
   * Subject and body are prefixed outside production.
   */
  public function testPrefixesSubjectAndBodyOutsideProduction(): void {
    putenv('APP_ENV=dev');
    $this->assertSame('[TEST] Offer accepted', OfferMailTestLabel::prefixSubject('Offer accepted'));
    $this->assertSame('[TEST] <p>Body</p>', OfferMailTestLabel::prefixBody('<p>Body</p>'));
  }

  /**
   * Subject and body are unchanged in production.
   */
  public function testDoesNotPrefixInProduction(): void {
    putenv('APP_ENV=production');
    $this->assertSame('Offer accepted', OfferMailTestLabel::prefixSubject('Offer accepted'));
    $this->assertSame('<p>Body</p>', OfferMailTestLabel::prefixBody('<p>Body</p>'));
  }

  /**
   * Empty APP_ENV is treated as non-production.
   */
  public function testEmptyAppEnvIsNonProduction(): void {
    putenv('APP_ENV');
    $this->assertFalse(OfferMailTestLabel::isProductionEnvironment());
    $this->assertSame('[TEST] x', OfferMailTestLabel::prefixSubject('x'));
  }

  /**
   * Data provider for production APP_ENV values.
   */
  public static function productionEnvironmentProvider(): array {
    return [
      ['prod'],
      ['production'],
      ['PROD'],
      ['Production'],
    ];
  }

  /**
   * Data provider for non-production APP_ENV values.
   */
  public static function nonProductionEnvironmentProvider(): array {
    return [
      ['dev'],
      ['local'],
      ['stg'],
      ['test'],
      ['testing'],
      ['development'],
    ];
  }

}
