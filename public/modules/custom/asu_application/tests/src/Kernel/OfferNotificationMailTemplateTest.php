<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_application\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests offer notification mail is rendered as plain text.
 *
 * @group asu_application
 */
final class OfferNotificationMailTemplateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'asu_api',
    'asu_application',
  ];

  /**
   * Mail body is plain text for each notification action.
   *
   * - Intro sentence matches the action.
   * - Project, apartment, customer and validity lines are present.
   * - HTML tags from the former template are not rendered.
   * - Ampersands in values are not HTML-entity encoded.
   *
   * @dataProvider actionProvider
   */
  public function testMailBodyIsPlainText(string $action, string $expectedIntro): void {
    $body = $this->renderMailBody($action, 'Foo & Bar');

    $this->assertStringContainsString($expectedIntro, $body);
    $this->assertStringContainsString('Project: Foo & Bar', $body);
    $this->assertStringContainsString('Apartment: A1', $body);
    $this->assertStringContainsString(
      'Customer: Jane Doe (jane@example.com)',
      $body,
    );
    $this->assertStringContainsString('Offer valid until: 2026-09-01', $body);
    $this->assertStringNotContainsString('<p>', $body);
    $this->assertStringNotContainsString('<ul>', $body);
    $this->assertStringNotContainsString('<li>', $body);
    $this->assertStringNotContainsString('</p>', $body);
    $this->assertStringNotContainsString('</ul>', $body);
    $this->assertStringNotContainsString('</li>', $body);
    $this->assertStringNotContainsString('&amp;', $body);
  }

  /**
   * Mail hook sets a plain-text content type and HTML-free body.
   *
   * - Content-Type is text/plain.
   * - Body contains the accepted-offer intro.
   * - Body does not contain HTML tags.
   */
  public function testMailHookUsesPlainTextContentType(): void {
    $message = [
      'to' => 'sales@example.com',
      'subject' => '',
      'body' => [],
      'headers' => [],
      'from' => '',
    ];
    $params = [
      'subject' => 'Customer accepted apartment offer',
      'project_name' => 'Test Project',
      'apartment_number' => 'A1',
      'customer_name' => 'Jane Doe',
      'customer_email' => 'jane@example.com',
      'valid_until' => '2026-09-01',
      'action' => 'accepted',
    ];

    asu_application_mail('offer_accepted_notification', $message, $params);

    $this->assertSame(
      'text/plain; charset=UTF-8',
      $message['headers']['Content-Type'],
    );
    $body = implode("\n", $message['body']);
    $this->assertStringContainsString(
      'A customer has accepted an apartment offer.',
      $body,
    );
    $this->assertStringNotContainsString('<p>', $body);
    $this->assertStringNotContainsString('<ul>', $body);
    $this->assertStringNotContainsString('<li>', $body);
  }

  /**
   * Notification actions and their expected intro sentences.
   */
  public static function actionProvider(): array {
    return [
      'accepted' => [
        'accepted',
        'A customer has accepted an apartment offer.',
      ],
      'rejected' => [
        'rejected',
        'A customer has rejected an apartment offer.',
      ],
      'reminder' => [
        'reminder',
        'A customer has not yet responded to an apartment offer before the deadline.',
      ],
    ];
  }

  /**
   * Render the offer notification mail theme.
   */
  private function renderMailBody(string $action, string $projectName): string {
    $build = [
      '#theme' => 'asu_application_offer_notification_mail',
      '#project_name' => $projectName,
      '#apartment_number' => 'A1',
      '#customer_name' => 'Jane Doe',
      '#customer_email' => 'jane@example.com',
      '#valid_until' => '2026-09-01',
      '#action' => $action,
    ];
    return (string) $this->container->get('renderer')->renderPlain($build);
  }

}
