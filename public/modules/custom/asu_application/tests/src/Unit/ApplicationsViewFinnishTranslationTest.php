<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Tests Finnish translations for the user applications listing.
 *
 * @group asu_application
 */
final class ApplicationsViewFinnishTranslationTest extends UnitTestCase {

  /**
   * Draft teaser uses a translatable "View the application" button label.
   *
   * - Template assigns the English source string.
   * - Label is passed through the Twig t filter.
   */
  public function testDraftTeaserViewApplicationStringIsTranslatable(): void {
    $template = file_get_contents($this->modulePath() . '/templates/asu-application.html.twig');
    $this->assertNotFalse($template);
    $this->assertStringContainsString(
      "{% set app_link_text = 'View the application' %}",
      $template
    );
    $this->assertMatchesRegularExpression(
      "/app_link_text = 'View the application'.*?label: app_link_text\\|t,/s",
      $template
    );
  }

  /**
   * Finnish translation file provides a non-empty msgstr for the button.
   */
  public function testViewTheApplicationHasFinnishTranslation(): void {
    $contents = file_get_contents($this->modulePath() . '/translations/fi.po');
    $this->assertNotFalse($contents);
    $this->assertMatchesRegularExpression(
      '/msgid "View the application"\nmsgstr "[^"]+"/',
      $contents
    );
  }

  /**
   * Absolute path to the asu_application module root.
   */
  private function modulePath(): string {
    return dirname(__DIR__, 3);
  }

}
