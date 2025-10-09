<?php

namespace Drupal\asu_application\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\CreateApplicationRequest;
use Drupal\asu_api\Api\BackendApi\Request\CreateUserRequest;
use Drupal\asu_api\Api\BackendApi\Request\SalesCreateApplicationRequest;
use Drupal\asu_api\ErrorCodeService;
use Drupal\asu_api\Exception\IllegalApplicationException;
use Drupal\asu_application\Event\ApplicationEvent;
use Drupal\asu_application\Event\SalesApplicationEvent;
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
   * The datetime.time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $timeService;

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
   * @param \Drupal\Component\Datetime\TimeInterface $time_service
   *   The datetime.time service.
   */
  public function __construct(
    LoggerInterface $logger,
    BackendApi $backendApi,
    QueueFactory $queueFactory,
    EntityTypeManagerInterface $entity_type_manager,
    ErrorCodeService $error_code_service,
    LanguageManager $languageManager,
    TimeInterface $time_service,
  ) {
    $this->logger = $logger;
    $this->backendApi = $backendApi;
    $this->queue = $queueFactory->get('application_api_queue');
    $this->entityTypeManager = $entity_type_manager;
    $this->errorCodeService = $error_code_service;
    $this->languageManager = $languageManager;
    $this->timeService = $time_service;
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
      $response = $this->backendApi->send($request);
      $this->logger->notice('Django response application_uuid: @uuid', [
        '@uuid' => $response->getContent()['application_uuid'] ?? 'NULL',
      ]);
      $application->set('field_backend_id', $response->getContent()['application_uuid'] ?? NULL);

      $application->set('field_locked', 1);
      $application->set('error', NULL);
      $application->set('create_to_django', $this->timeService->getRequestTime());
      $application->save();

      try {
        /** @var \Drupal\user\UserInterface|null $owner */
        $owner = $application->getOwner();
        $to = $owner ? $owner->getEmail() : NULL;

        if ($to) {
          $project_name = '';
          if ($application->hasField('project') && ($proj = $application->get('project')->entity)) {
            $project_name = $proj->label();
          }
          elseif ($application->hasField('project_id') && ($nid = (int) ($application->get('project_id')->value ?? 0))) {
            if ($nid > 0) {
              // phpcs:ignore DrupalPractice.Objects.GlobalDrupal
              $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
              if ($node) {
                $project_name = $node->label();
              }
            }
          }
          if ($project_name === '' || $project_name === NULL) {
            $project_name = $this->t('our project');
          }

          /** @var \Drupal\Core\Mail\MailManagerInterface $mailManager */
          // phpcs:ignore DrupalPractice.Objects.GlobalDrupal
          $mailManager = \Drupal::service('plugin.manager.mail');
          // phpcs:ignore DrupalPractice.Objects.GlobalDrupal
          $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();

          $params = [];
          $params['subject'] = $this->t('Kiitos hakemuksestasi / Thank you for your application');
          $params['message_lines'] = [
            // FI.
            $this->t('Kiitos - olemme vastaanottaneet hakemuksesi kohteeseemme @project_name.', ['@project_name' => $project_name]),
            '',
            $this->t('Hakemuksesi on voimassa koko rakennusajan.'),
            '',
            $this->t('Arvonnan / huoneistojaon jälkeen voit tarkastaa oman sijoituksesi kirjautumalla kotisivuillemme:'),
            'asuntotuotanto.hel.fi.',
            '',
            '------------------------------------------------------------',
            '',
            // EN.
            $this->t('Thank you - we have received your application for @project_name.', ['@project_name' => $project_name]),
            '',
            $this->t('Your application will remain valid throughout the construction period.'),
            '',
            $this->t('After the lottery / apartment distribution, you can check your position by logging into our website:'),
            'asuntotuotanto.hel.fi.',
            '',
            $this->t('This is an automated message – please do not reply to this email.'),
          ];

          $mailManager->mail('asu_application', 'application_submission', $to, $langcode, $params, NULL, TRUE);

          $result = $mailManager->mail('asu_application', 'application_submission', $to, $langcode, $params, NULL, TRUE);

          if (!empty($result['result'])) {
            // phpcs:ignore DrupalPractice.Objects.GlobalDrupal
            \Drupal::logger('asu_application')->notice(
              'Confirmation email sent for application @id to @to (lang: @lang). Subject: @subject. Project: @project.',
              [
                '@id' => $application->id(),
                '@to' => $to,
                '@lang' => $langcode,
                '@subject' => $params['subject'],
                '@project' => $project_name,
              ]
            );
          }
          else {
            // phpcs:ignore DrupalPractice.Objects.GlobalDrupal
            \Drupal::logger('asu_application')->warning(
              'Confirmation email FAILED (no exception) for application @id to @to (lang: @lang). Subject: @subject. Project: @project.',
              [
                '@id' => $application->id(),
                '@to' => $to,
                '@lang' => $langcode,
                '@subject' => $params['subject'],
                '@project' => $project_name,
              ]
            );
          }

        }
      }
      catch (\Throwable $e) {
        // phpcs:ignore DrupalPractice.Objects.GlobalDrupal
        \Drupal::logger('asu_application')->warning('Confirmation email was not sent for application @id: @err', [
          '@id' => $application->id(),
          '@err' => $e->getMessage(),
        ]);
      }

      // Clean sensitive data from application.
      $application->cleanSensitiveInformation();

      $this->logger->notice(
        'User sent an application to backend successfully'
      );

      $this->messenger()->addMessage(
      $this->t('The application period has ended.') . ' ' .
      $this->t('You can still apply for the apartment by contacting the responsible salesperson.')
      );

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

      $response = $this->backendApi->send($request);
      $application->set('field_backend_id', $response->getContent()['application_uuid'] ?? NULL);
      $this->logger->notice(
       'Sales sent application to backend successfully'
      );

      $application->set('field_locked', 1);
      $application->set('error', NULL);
      $application->set('create_to_django', $this->timeService->getRequestTime());
      $application->save();
      // Clean sensitive data from application.
      $application->cleanSensitiveInformation();

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
