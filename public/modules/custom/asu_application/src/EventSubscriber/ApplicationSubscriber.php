<?php

namespace Drupal\asu_application\EventSubscriber;

use Drupal\asu_api\Api\BackendApi\Request\CreateApplicationRequest;
use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\SalesCreateApplicationRequest;
use Drupal\asu_api\Exception\IllegalApplicationException;
use Drupal\asu_application\Event\ApplicationEvent;
use Drupal\asu_application\Event\SalesApplicationEvent;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Application subscriber.
 */
class ApplicationSubscriber implements EventSubscriberInterface {
  use MessengerTrait;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * Backend api.
   *
   * @var Drupal\asu_api\Api\BackendApi\BackendApi
   */
  private BackendApi $backendApi;

  /**
   * Queueworker.
   *
   * @var Drupal\Core\Queue\QueueInterface
   */
  private QueueInterface $queue;

  /**
   * Constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\asu_api\Api\BackendApi\BackendApi $backendApi
   *   Api manager.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue factory.
   */
  public function __construct(LoggerInterface $logger, BackendApi $backendApi, QueueFactory $queueFactory) {
    $this->logger = $logger;
    $this->backendApi = $backendApi;
    $this->queue = $queueFactory->get('application_api_queue');
  }

  /**
   * Get subscribed events.
   *
   * @return array
   *   The event names to listen to.
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[ApplicationEvent::EVENT_NAME][] = ['sendApplicationToBackend', 5];
    $events[SalesApplicationEvent::EVENT_NAME][] = [
      'salesSendApplicationToBackend',
      10,
    ];
    return $events;
  }

  /**
   * Sends application to backend.
   *
   * @param \Drupal\asu_application\Event\ApplicationEvent $applicationEvent
   *   Application event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function sendApplicationToBackend(ApplicationEvent $applicationEvent) {
    $entity_type = 'asu_application';
    $entity_id = $applicationEvent->getApplicationId();

    /** @var \Drupal\asu_application\Entity\Application $application */
    $application = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
    $user = $application->getOwner();

    try {
      $request = new CreateApplicationRequest(
        $user,
        $application,
        [
          'uuid' => $applicationEvent->getProjectUuid(),
          'apartment_uuids' => $applicationEvent->getApartmentUuids(),
        ]
      );
      $request->setSender($user);
      $this->backendApi->send($request);

      $this->logger->notice(
        'User sent an application to backend successfully'
      );
    }
    catch (IllegalApplicationException $e) {
      $code = $e->getApiErrorCode();
      /** @var \Drupal\asu_api\ErrorCodeService $errorCodeService */

      $this->logger->info(sprintf(
          'Illegal application error with code %s: %s',
          $code,
          $e->getMessage())
      );

      $errorCodeService = \Drupal::service('asu_api.error_code_service');
      $langCode = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $message = $errorCodeService->getErrorMessageByCode($code, $langCode);

      if ($message) {
        $this->messenger->addError($message);
      }
      else {
        $this->logger->critical(
          'Unable to resolve error code from response message: ' . $e->getMessage()
        );
      }

    }
    catch (\Exception $e) {
      $this->logger->critical(sprintf(
        'Exception while sending application of id %s: %s',
        $application->id(),
        $e->getMessage()
      ));
      $this->queue->createItem($application->id());
    }

  }

  /**
   * Sales person sends application for customer.
   */
  public function salesSendApplicationToBackend(SalesApplicationEvent $applicationEvent) {
    $entity_type = 'asu_application';
    $entity_id = $applicationEvent->getApplicationId();

    $sender = User::load($applicationEvent->getSenderId());

    /** @var \Drupal\asu_application\Entity\Application $application */
    $application = \Drupal::entityTypeManager()
      ->getStorage($entity_type)
      ->load($entity_id);

    try {
      $request = new SalesCreateApplicationRequest(
        $sender,
        $application,
        [
          'uuid' => $applicationEvent->getProjectUuid(),
          'apartment_uuids' => $applicationEvent->getApartmentUuids(),
        ]
      );

      $request->setSender($sender);

      $this->backendApi->send($request);
      $this->logger->notice(
       'Sales sent application to backend successfully'
      );

      $application->set('field_locked', 1);
      $application->save();
      $this->messenger()->addStatus($this->t('The application has been submitted successfully.
     You can no longer edit the application.'));

    }
    catch (IllegalApplicationException $e) {
      $code = $e->getApiErrorCode();
      /** @var \Drupal\asu_api\ErrorCodeService $errorCodeService */

      $this->logger->info(sprintf(
          'Illegal application error with code %s: %s',
          $code,
          $e->getMessage())
      );

      $errorCodeService = \Drupal::service('asu_api.error_code_service');
      $langCode = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $message = $errorCodeService->getErrorMessageByCode($code, $langCode);

      if ($message) {
        $this->messenger->addError($message);
      }
      else {
        $this->logger->critical(
          'Unable to resolve error code from response message: ' . $e->getMessage()
        );
        $this->messenger->addError(
          t('Illegal application error while creating application. ' . $e->getMessage())
        );
      }

    }
    catch (\Exception $e) {
      $this->logger->critical(sprintf(
        'Exception while sending application %s: %s',
        $application->id(),
        $e->getMessage()
      ));
      $this->queue->createItem($application->id());
    }

  }

}
