<?php

namespace Drupal\Tests\asu_application\Unit;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Response\MarkOfferMessageSentResponse;
use Drupal\asu_api\Api\BackendApi\Response\PendingOfferMessagesResponse;
use Drupal\asu_application\Service\OfferMessageService;
use Drupal\asu_application\Service\OfferNotificationService;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests OfferMessageService cron logic.
 *
 * @group asu_application
 */
final class OfferMessageServiceTest extends UnitTestCase {

  /**
   * Processes messages and marks them sent only after successful mail.
   */
  public function testProcessesMessagesAndMarksSent(): void {
    $notification = $this->createMock(OfferNotificationService::class);
    $notification->expects($this->exactly(2))
      ->method('sendOfferCreatedNotification')
      ->willReturnCallback(function (string $recipients, string $subject, string $body): bool {
        static $call = 0;
        $call++;
        if ($call === 1) {
          $this->assertSame('a@example.com', $recipients);
          $this->assertSame('Tarjous As Oy 1', $subject);
          $this->assertSame('Offer body one', $body);
        }
        else {
          $this->assertSame('b@example.com,c@example.com', $recipients);
          $this->assertSame('Tarjous As Oy 2', $subject);
          $this->assertSame('Offer body two', $body);
        }
        return TRUE;
      });

    $backend = $this->createMock(BackendApi::class);
    $backend->expects($this->exactly(3))
      ->method('send')
      ->willReturnOnConsecutiveCalls(
        new PendingOfferMessagesResponse([
          [
            'id' => 1,
            'subject' => 'Tarjous As Oy 1',
            'body' => 'Offer body one',
            'recipients' => [
              ['name' => 'A', 'email' => 'a@example.com'],
            ],
          ],
          [
            'id' => 2,
            'subject' => 'Tarjous As Oy 2',
            'body' => 'Offer body two',
            'recipients' => [
              ['name' => 'B', 'email' => 'b@example.com'],
              ['name' => 'C', 'email' => 'c@example.com'],
            ],
          ],
        ]),
        new MarkOfferMessageSentResponse(['id' => 1]),
        new MarkOfferMessageSentResponse(['id' => 2]),
      );

    $service = new OfferMessageService(
      $backend,
      $notification,
      $this->createMock(LoggerChannelInterface::class),
    );

    $service->processDueMessages();
  }

  /**
   * Does not mark sent when mail delivery fails.
   */
  public function testDoesNotMarkSentWhenMailFails(): void {
    $notification = $this->createMock(OfferNotificationService::class);
    $notification->expects($this->once())
      ->method('sendOfferCreatedNotification')
      ->willReturn(FALSE);

    $backend = $this->createMock(BackendApi::class);
    $backend->expects($this->once())
      ->method('send')
      ->willReturn(new PendingOfferMessagesResponse([
        [
          'id' => 1,
          'subject' => 'Tarjous As Oy 1',
          'body' => 'Offer body one',
          'recipients' => [
            ['name' => 'A', 'email' => 'a@example.com'],
          ],
        ],
      ]));

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())->method('error');

    $service = new OfferMessageService(
      $backend,
      $notification,
      $logger,
    );

    $service->processDueMessages();
  }

}
