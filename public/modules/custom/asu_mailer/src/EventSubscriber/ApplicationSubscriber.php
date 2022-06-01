<?php

namespace Drupal\asu_mailer\EventSubscriber;

use Drupal\asu_application\Event\ApplicationEvent;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Eventsubscriber for sending email after application event.
 */
class ApplicationSubscriber implements EventSubscriberInterface {
  private const HASO_APPLICATION_CREATED_SUBJECT = 'haso_application_created_subject';
  private const HASO_APPLICATION_CREATED_TEXT = 'haso_application_created_text';
  private const HITAS_APPLICATION_CREATED_SUBJECT = 'hitas_application_created_subject';
  private const HITAS_APPLICATION_CREATED_TEXT = 'hitas_application_created_text';

  /**
   * Get subscribed events.
   *
   * @return array
   *   The event names to listen to.
   */
  public static function getSubscribedEvents() {
    $events = [];
    //$events[ApplicationEvent::EVENT_NAME][] =
      //['sendApplicationCreatedEmailToCustomer', 20];
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
    /** @var \Drupal\Core\Mail\MailManager $mailManager */
    $mailManager = \Drupal::service('plugin.manager.mail');
    $config = \Drupal::config('asu_mailer.email_content_settings');

    /** @var \Drupal\asu_application\Entity\Application $application */
    $application = \Drupal::entityTypeManager()->getStorage('asu_application')->load($applicationEvent->getApplicationId());
    /** @var \Drupal\user\Entity\User $user */
    $user = User::load($application->getOwner()->id());
    $langcode = $user->getPreferredLangcode();

    if ($application->bundle() == 'haso') {
      $subjectConfigKey = $this::HASO_APPLICATION_CREATED_SUBJECT . '_' . $langcode;
      $textConfigKey = $this::HASO_APPLICATION_CREATED_TEXT . '_' . $langcode;
      $subject = $config->get($subjectConfigKey);
      $text = $config->get($textConfigKey);
    }
    elseif ($application->bundle() == 'hitas') {
      $subjectConfigKey = $this::HITAS_APPLICATION_CREATED_SUBJECT . '_' . $langcode;
      $textConfigKey = $this::HITAS_APPLICATION_CREATED_TEXT . '_' . $langcode;
      $subject = $config->get($subjectConfigKey);
      $text = $config->get($textConfigKey);
    }

    $module = 'asu_mailer';
    $key = 'application_user_confirmation';
    $to = $user->getEmail();
    $send = TRUE;
    $params = [
      'subject' => $subject,
      'message' => $text,
    ];

    $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

    if ($result['result'] != TRUE) {
      // Email sending failed.
      \Drupal::messenger()->addMessage('Email sending failed. Most likely due to misconfigured email system.');
      // @todo Add logging.
      return;
    }
  }

}
