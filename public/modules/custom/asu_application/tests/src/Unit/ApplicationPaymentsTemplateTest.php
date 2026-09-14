<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Verifies payments UI is present in application teaser template.
 *
 * @group asu_application
 */
final class ApplicationPaymentsTemplateTest extends UnitTestCase {

  /**
   * Template contains payments button, table and empty-state output.
   */
  public function testSubmittedTeaserContainsPaymentsUiBlocks(): void {
    $template = file_get_contents($this->modulePath() . '/templates/asu-application.html.twig');
    $this->assertNotFalse($template);

    $this->assertStringContainsString('application__payments-link--toggle', $template);
    $this->assertStringContainsString('application__payments-table', $template);
    $this->assertStringContainsString("application_payments_ui.empty", $template);
    $this->assertStringContainsString("application_payments_ui.installment_type", $template);
  }

  /**
   * Absolute path to the asu_application module root.
   */
  private function modulePath(): string {
    return dirname(__DIR__, 3);
  }

}
