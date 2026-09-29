<?php

namespace Drupal\Tests\asu_application\Unit;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Response\CreateApplicationResponse;
use Drupal\asu_api\ErrorCodeService;
use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Event\ApplicationEvent;
use Drupal\asu_application\EventSubscriber\ApplicationSubscriber;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests application submission confirmation mail sending.
 *
 * @group asu_application
 *
 * @coversDefaultClass \Drupal\asu_application\EventSubscriber\ApplicationSubscriber
 */
final class ApplicationSubscriberConfirmationMailTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
    parent::tearDown();
  }

  /**
   * Confirmation email is sent exactly once after a successful backend submit.
   *
   * - Backend create-application call succeeds.
   * - Owner has an email address.
   * - Mail manager is invoked once with key application_submission.
   */
  public function testConfirmationEmailIsSentOnceOnSuccessfulSubmit(): void {
    $mailManager = $this->createMock(MailManagerInterface::class);
    $mailManager->expects($this->once())
      ->method('mail')
      ->with(
        'asu_application',
        'application_submission',
        'applicant@example.com',
        'fi',
        $this->callback(static function (array $params): bool {
          return isset($params['subject'], $params['message_lines'])
            && is_array($params['message_lines']);
        }),
        NULL,
        TRUE
      )
      ->willReturn(['result' => TRUE]);

    $application = $this->createApplication('applicant@example.com');
    $subscriber = $this->createSubscriber($mailManager);
    $subscriber->sendApplicationToBackend($this->createEvent($application));
  }

  /**
   * Confirmation email is not sent when the owner has no email address.
   *
   * - Backend create-application call succeeds.
   * - Owner email is empty.
   * - Mail manager is never invoked.
   */
  public function testConfirmationEmailIsNotSentWhenOwnerHasNoEmail(): void {
    $mailManager = $this->createMock(MailManagerInterface::class);
    $mailManager->expects($this->never())->method('mail');

    $application = $this->createApplication(NULL);
    $subscriber = $this->createSubscriber($mailManager);
    $subscriber->sendApplicationToBackend($this->createEvent($application));
  }

  /**
   * Build a subscriber with mocked backend success and Drupal mail services.
   */
  private function createSubscriber(MailManagerInterface $mailManager): ApplicationSubscriber {
    $project = $this->createMock(NodeInterface::class);
    $project->method('uuid')->willReturn('project-uuid');
    $project->method('label')->willReturn('Test Project');

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('load')->willReturn($project);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($nodeStorage);

    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('fi');

    $languageManager = $this->createMock(LanguageManager::class);
    $languageManager->method('getDefaultLanguage')->willReturn($language);

    $loggerChannel = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($loggerChannel);

    $container = new ContainerBuilder();
    $container->set('plugin.manager.mail', $mailManager);
    $container->set('language_manager', $languageManager);
    $container->set('logger.factory', $loggerFactory);
    $container->set('messenger', $this->createMock(MessengerInterface::class));
    $container->set('entity_type.manager', $entityTypeManager);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $response = new CreateApplicationResponse(['application_uuid' => 'app-uuid']);
    $backendApi = $this->createMock(BackendApi::class);
    $backendApi->method('send')->willReturn($response);

    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->method('get')->willReturn($this->createMock(QueueInterface::class));

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);

    $subscriber = new ApplicationSubscriber(
      $this->createMock(LoggerInterface::class),
      $backendApi,
      $queueFactory,
      $entityTypeManager,
      $this->createMock(ErrorCodeService::class),
      $languageManager,
      $time,
    );
    $subscriber->setStringTranslation($this->getStringTranslationStub());

    return $subscriber;
  }

  /**
   * Create an application mock with an optional owner email.
   */
  private function createApplication(?string $email): Application {
    $owner = $this->createMock(UserInterface::class);
    $owner->method('getEmail')->willReturn($email);

    $projectNode = $this->createMock(NodeInterface::class);
    $projectNode->method('label')->willReturn('Test Project');
    $projectField = new \stdClass();
    $projectField->entity = $projectNode;

    $application = $this->createMock(Application::class);
    $application->method('getOwner')->willReturn($owner);
    $application->method('id')->willReturn(42);
    $application->method('hasField')->willReturnCallback(static function (string $name): bool {
      return $name === 'project' || $name === 'project_id';
    });
    $application->method('get')->willReturnCallback(static function (string $name) use ($projectField) {
      return $name === 'project' ? $projectField : NULL;
    });
    $application->method('set')->willReturnSelf();
    $application->method('__get')->willReturnCallback(static function (string $name) {
      return $name === 'project_id' ? (object) ['value' => 10] : NULL;
    });

    return $application;
  }

  /**
   * Create an application event wrapping the given application.
   */
  private function createEvent(Application $application): ApplicationEvent {
    return new ApplicationEvent('42', 'Test Project', 'project-uuid', $application);
  }

}
