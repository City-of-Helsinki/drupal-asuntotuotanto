<?php

namespace Drupal\Tests\asu_application\Unit;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Response\MarkOfferReminderSentResponse;
use Drupal\asu_api\Api\BackendApi\Response\PendingOfferRemindersResponse;
use Drupal\asu_application\Service\OfferNotificationService;
use Drupal\asu_application\Service\OfferReminderService;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests OfferReminderService cron logic.
 *
 * @group asu_application
 */
final class OfferReminderServiceTest extends UnitTestCase {

  /**
   * Daily throttle prevents a second run on the same day.
   */
  public function testDailyThrottleSkipsSecondRun(): void {
    $state = $this->createMock(StateInterface::class);
    $state->expects($this->once())
      ->method('get')
      ->with(OfferReminderService::STATE_KEY_LAST_RUN)
      ->willReturn(date('Y-m-d'));

    $backend = $this->createMock(BackendApi::class);
    $backend->expects($this->never())->method('send');

    $service = new OfferReminderService(
      $backend,
      $this->createMock(OfferNotificationService::class),
      $this->createMock(EntityRepositoryInterface::class),
      $state,
      $this->createMock(LoggerChannelInterface::class),
    );

    $service->processDueReminders();
  }

  /**
   * Processes reminders and marks them sent in Django.
   */
  public function testProcessesRemindersAndMarksSent(): void {
    $state = $this->createMock(StateInterface::class);
    $state->expects($this->once())->method('get')->willReturn('2000-01-01');
    $state->expects($this->once())->method('set')->with(
      OfferReminderService::STATE_KEY_LAST_RUN,
      date('Y-m-d')
    );

    $project = $this->createMock(NodeInterface::class);
    $entityRepository = $this->createMock(EntityRepositoryInterface::class);
    $entityRepository->method('loadEntityByUuid')
      ->willReturn($project);

    $notification = $this->createMock(OfferNotificationService::class);
    $notification->expects($this->exactly(2))
      ->method('sendReminderNotification')
      ->with($project, $this->isType('array'));

    $backend = $this->createMock(BackendApi::class);
    $backend->expects($this->exactly(3))
      ->method('send')
      ->willReturnOnConsecutiveCalls(
        new PendingOfferRemindersResponse([
          [
            'id' => 1,
            'project_uuid' => '11111111-1111-1111-1111-111111111111',
            'apartment_uuid' => '22222222-2222-2222-2222-222222222222',
            'valid_until' => '2099-01-01',
            'customer' => ['primary_profile' => ['email' => 'a@example.com']],
          ],
          [
            'id' => 2,
            'project_uuid' => '11111111-1111-1111-1111-111111111111',
            'apartment_uuid' => '33333333-3333-3333-3333-333333333333',
            'valid_until' => '2099-01-02',
            'customer' => ['primary_profile' => ['email' => 'b@example.com']],
          ],
        ]),
        new MarkOfferReminderSentResponse(['id' => 1]),
        new MarkOfferReminderSentResponse(['id' => 2]),
      );

    $service = new OfferReminderService(
      $backend,
      $notification,
      $entityRepository,
      $state,
      $this->createMock(LoggerChannelInterface::class),
    );

    $service->processDueReminders();
  }

}
