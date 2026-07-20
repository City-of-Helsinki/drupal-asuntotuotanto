<?php

namespace Drupal\Tests\asu_application\Unit;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Response\MarkOfferMessageSentResponse;
use Drupal\asu_api\Api\BackendApi\Response\PendingOfferMessagesResponse;
use Drupal\asu_application\Service\OfferMessageService;
use Drupal\asu_application\Service\OfferNotificationService;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests OfferMessageService cron logic.
 *
 * @group asu_application
 */
final class OfferMessageServiceTest extends UnitTestCase {

  /**
   * Daily throttle prevents a second run on the same day.
   */
  public function testDailyThrottleSkipsSecondRun(): void {
    $state = $this->createMock(StateInterface::class);
    $state->expects($this->once())
      ->method('get')
      ->with(OfferMessageService::STATE_KEY_LAST_RUN)
      ->willReturn(date('Y-m-d'));

    $backend = $this->createMock(BackendApi::class);
    $backend->expects($this->never())->method('send');

    $service = new OfferMessageService(
      $backend,
      $this->createMock(OfferNotificationService::class),
      $state,
      $this->createMock(LoggerChannelInterface::class),
    );

    $service->processDueMessages();
  }

  /**
   * Processes messages and marks them sent in Django.
   */
  public function testProcessesMessagesAndMarksSent(): void {
    $state = $this->createMock(StateInterface::class);
    $state->expects($this->once())->method('get')->willReturn('2000-01-01');
    $state->expects($this->once())->method('set')->with(
      OfferMessageService::STATE_KEY_LAST_RUN,
      date('Y-m-d')
    );

    $notification = $this->createMock(OfferNotificationService::class);
    $notification->expects($this->exactly(2))
      ->method('sendOfferCreatedNotification')
      ->with(
        $this->logicalOr('a@example.com', 'b@example.com,c@example.com'),
        $this->isType('string'),
        $this->isType('string'),
      );

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
      $state,
      $this->createMock(LoggerChannelInterface::class),
    );

    $service->processDueMessages();
  }

}
