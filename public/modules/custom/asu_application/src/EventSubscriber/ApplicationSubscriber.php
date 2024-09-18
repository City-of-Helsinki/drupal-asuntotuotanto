<?php

namespace Drupal\asu_application\EventSubscriber;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\CreateApplicationRequest;
use Drupal\asu_api\Api\BackendApi\Request\CreateUserRequest;
use Drupal\asu_api\Api\BackendApi\Request\SalesCreateApplicationRequest;
use Drupal\asu_api\ErrorCodeService;
use Drupal\asu_api\Exception\IllegalApplicationException;
use Drupal\asu_application\Event\ApplicationEvent;
use Drupal\asu_application\Event\SalesApplicationEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Application subscriber.
 */
class ApplicationSubscriber implements EventSubscriberInterface {
  use MessengerTrait;
  use StringTranslationTrait;

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
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The asu error service.
   *
   * @var \Drupal\asu_api\ErrorCodeService
   */
  protected $errorCodeService;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * Constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\asu_api\Api\BackendApi\BackendApi $backendApi
   *   Api manager.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\asu_api\ErrorCodeService $error_code_service
   *   Asu error service.
   * @param \Drupal\Core\Language\LanguageManager $languageManager
   *   Language manager.
   */
  public function __construct(
    LoggerInterface $logger,
    BackendApi $backendApi,
    QueueFactory $queueFactory,
    EntityTypeManagerInterface $entity_type_manager,
    ErrorCodeService $error_code_service,
    LanguageManager $languageManager,
  ) {
    $this->logger = $logger;
    $this->backendApi = $backendApi;
    $this->queue = $queueFactory->get('application_api_queue');
    $this->entityTypeManager = $entity_type_manager;
    $this->errorCodeService = $error_code_service;
    $this->languageManager = $languageManager;
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
    /** @var \Drupal\asu_application\Entity\Application $application */
    $application = $applicationEvent->getApplication();

    $project = $this->entityTypeManager->getStorage("node")->load($application->project_id->value);
    $user = $application->getOwner();

    try {
      $request = new CreateApplicationRequest(
        $user,
        $application,
        $project->uuid(),
      );

      $request->setSender($user);
      $this->backendApi->send($request);

      $application->set('field_locked', 1);
      $application->set('error', NULL);
      $application->set('create_to_django', \Drupal::time()->getCurrentTime());
      $application->save();

      $this->logger->notice(
        'User sent an application to backend successfully'
      );

      $this->messenger()->addMessage($this->t('Your application has been received. We will contact you when all the application has been processed.'));
    }
    catch (IllegalApplicationException $e) {
      $code = $e->getApiErrorCode();
      $errorCodeService = $this->errorCodeService;
      $langCode = $this->languageManager->getCurrentLanguage()->getId();
      $message = $errorCodeService->getErrorMessageByCode($code, $langCode);

      if ($message) {
        $this->messenger()->addError($message);
      }
      else {
        $this->logger->critical(
          'Unable to resolve error code from response message for application' .
          $application->id() .
          ': ' .
          $e->getMessage()
        );
        $message = 'Undefined exception while sending application';
        $this->messenger()->addError($this->t('Unfortunately we were unable to handle your application.'));
      }

      $application->set('error', $message);
      $application->save();

    }
    catch (\Exception $e) {
      $this->logger->critical(sprintf(
        'Exception while sending application of id %s: %s',
        $application->id(),
        $e->getMessage()
      ));

      $application->set('error', 'Undefined exception while sending application');
      $application->save();

      $this->messenger()->addError($this->t('Unfortunately we were unable to handle your application.'));
      $this->queue->createItem($application->id());
    }
  }

  /**
   * Sales person sends application for customer.
   */
  public function salesSendApplicationToBackend(SalesApplicationEvent $applicationEvent) {
    $entity_type = 'asu_application';
    $entity_id = $applicationEvent->getApplicationId();

    $sender = $this->entityTypeManager->getStorage("user")->load($applicationEvent->getSenderId());

    /** @var \Drupal\asu_application\Entity\Application $application */
    $application = $this->entityTypeManager
      ->getStorage($entity_type)
      ->load($entity_id);

    $project = $this->entityTypeManager->getStorage("node")->load($application->project_id->value);

    try {
      $request = new SalesCreateApplicationRequest(
        $sender,
        $application,
        $project->uuid(),
      );

      $customer = $this->entityTypeManager->getStorage("user")->load($application->getOwnerId());
      $accountData = [
        'first_name' => '',
        'last_name' => '',
        'date_of_birth' => '',
      ];

      if (empty($customer->field_backend_profile->value)) {
        $request = new CreateUserRequest(
          $customer,
          $accountData,
          'customer'
        );

        try {
          $response = $this->backendApi->send($request);
          $customer->field_backend_profile = $response->getProfileId();
          $customer->field_backend_password = $response->getPassword();
          $customer->save();
        }
        catch (\Exception $e) {
          $this->logger->emergency(
            'Exception while creating user to backend: ' . $e->getMessage()
          );
        }
      }

      $request->setSender($sender);

      $this->backendApi->send($request);
      $this->logger->notice(
       'Sales sent application to backend successfully'
      );

      $application->set('field_locked', 1);
      $application->set('error', NULL);
      $application->set('create_to_django', \Drupal::time()->getCurrentTime());
      $application->save();

      $this->messenger()->addStatus($this->t('The application has been submitted successfully.
     You can no longer edit the application.'));

    }
    catch (IllegalApplicationException $e) {
      $code = $e->getApiErrorCode();

      $this->logger->info(sprintf(
          'Illegal application error with code %s: %s',
          $code,
          $e->getMessage())
      );

      $errorCodeService = $this->errorCodeService;
      $langCode = $this->languageManager->getCurrentLanguage()->getId();
      $message = $errorCodeService->getErrorMessageByCode($code, $langCode);

      if ($message) {
        $this->messenger()->addError($message);
      }
      else {
        $this->logger->critical(sprintf(
          'Unable to resolve error code from response message: Code: % - %',
          $code,
          $e->getMessage()
          )
        );
        $message = "Illegal application error while creating application. Unable to resolve error code.";
        $this->messenger()->addError($message);
      }
      $application->set('error', $message);
      $application->save();
    }
    catch (\Exception $e) {
      $this->logger->critical(sprintf(
        'Exception while sending application %s: %s',
        $application->id(),
        $e->getMessage()
      ));

      $message = 'Unexpected exception while sending application. ';
      $application->set('error', $message);
      $application->save();

      $this->messenger()->addError(
        $message . $e->getMessage()
      );
      $this->queue->createItem($application->id());
    }

  }

}
