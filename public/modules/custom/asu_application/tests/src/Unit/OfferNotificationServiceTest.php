<?php

namespace Drupal\Tests\asu_application\Unit;

use Drupal\asu_application\Service\OfferNotificationService;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests OfferNotificationService mail success handling.
 *
 * @group asu_application
 */
final class OfferNotificationServiceTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    putenv('APP_ENV');
    parent::tearDown();
  }

  /**
   * Console-logged dev mail is treated as successful delivery.
   */
  public function testDevConsoleMailCountsAsSuccess(): void {
    putenv('APP_ENV=dev');

    $mailManager = $this->createMock(MailManagerInterface::class);
    $mailManager->expects($this->once())
      ->method('mail')
      ->willReturn(['result' => FALSE]);

    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('fi');

    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $languageManager->method('getDefaultLanguage')->willReturn($language);

    $service = new OfferNotificationService(
      $mailManager,
      $languageManager,
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityRepositoryInterface::class),
      $this->createMock(LoggerChannelInterface::class),
    );

    $this->assertTrue($service->sendOfferCreatedNotification(
      'customer@example.com',
      'Tarjous',
      'Body',
    ));
  }

  /**
   * Failed mail outside dev is not treated as successful delivery.
   */
  public function testFailedMailOutsideDevIsNotSuccess(): void {
    putenv('APP_ENV=production');

    $mailManager = $this->createMock(MailManagerInterface::class);
    $mailManager->expects($this->once())
      ->method('mail')
      ->willReturn(['result' => FALSE]);

    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('fi');

    $languageManager = $this->createMock(LanguageManagerInterface::class);
    $languageManager->method('getDefaultLanguage')->willReturn($language);

    $service = new OfferNotificationService(
      $mailManager,
      $languageManager,
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityRepositoryInterface::class),
      $this->createMock(LoggerChannelInterface::class),
    );

    $this->assertFalse($service->sendOfferCreatedNotification(
      'customer@example.com',
      'Tarjous',
      'Body',
    ));
  }

}
