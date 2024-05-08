<?php

namespace Drupal\asu_mailer\EventSubscriber;

use Drupal\asu_application\Event\ApplicationEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Eventsubscriber for sending email after application event.
 */
class ApplicationSubscriber implements EventSubscriberInterface {
  use MessengerTrait;

  private const HASO_APPLICATION_CREATED_SUBJECT = 'haso_application_created_subject';
  private const HASO_APPLICATION_CREATED_TEXT = 'haso_application_created_text';
  private const HITAS_APPLICATION_CREATED_SUBJECT = 'hitas_application_created_subject';
  private const HITAS_APPLICATION_CREATED_TEXT = 'hitas_application_created_text';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManager
   */
  protected $mailManager;

  /**
   * The admin toolbar tools configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MailManagerInterface $mail_manager,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->config = $config_factory->get('asu_mailer.email_content_settings');
  }

  /**
   * Get subscribed events.
   *
   * @return array
   *   The event names to listen to.
   */
  public static function getSubscribedEvents() {
    $events = [];

    return $events;
  }

  /**
   * Send email to customer after application is submitted.
   *
   * @param \Drupal\asu_application\Event\ApplicationEvent $applicationEvent
   *   Application event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function sendApplicationCreatedEmailToCustomer(ApplicationEvent $applicationEvent) {
    /** @var \Drupal\asu_application\Entity\Application $application */
    $application = $this->entityTypeManager->getStorage('asu_application')->load($applicationEvent->getApplicationId());
    /** @var \Drupal\user\Entity\User $user */
    $user = $this->entityTypeManager->getStorage('user')->load($application->getOwner()->id());
    $langcode = $user->getPreferredLangcode();

    if ($application->bundle() == 'haso') {
      $subjectConfigKey = $this::HASO_APPLICATION_CREATED_SUBJECT . '_' . $langcode;
      $textConfigKey = $this::HASO_APPLICATION_CREATED_TEXT . '_' . $langcode;
      $subject = $this->config->get($subjectConfigKey);
      $text = $this->config->get($textConfigKey);
    }
    elseif ($application->bundle() == 'hitas') {
      $subjectConfigKey = $this::HITAS_APPLICATION_CREATED_SUBJECT . '_' . $langcode;
      $textConfigKey = $this::HITAS_APPLICATION_CREATED_TEXT . '_' . $langcode;
      $subject = $this->config->get($subjectConfigKey);
      $text = $this->config->get($textConfigKey);
    }

    $module = 'asu_mailer';
    $key = 'application_user_confirmation';
    $to = $user->getEmail();
    $send = TRUE;
    $params = [
      'subject' => $subject,
      'message' => $text,
    ];

    $result = $this->mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

    if ($result['result'] != TRUE) {
      // Email sending failed.
      $this->messenger()->addMessage('Email sending failed. Most likely due to misconfigured email system.');
      // @todo Add logging.
      return;
    }
  }

}
