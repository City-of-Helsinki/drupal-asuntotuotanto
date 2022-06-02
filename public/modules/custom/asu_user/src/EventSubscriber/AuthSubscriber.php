<?php

namespace Drupal\asu_user\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\samlauth\Event\SamlauthEvents;
use Drupal\samlauth\Event\SamlauthUserSyncEvent;

class AuthSubscriber implements EventSubscriberInterface {

  public function __construct() {
  }

  public static function getSubscribedEvents() {
    $events = [];
    $events[SamlauthEvents::USER_SYNC][] = ['syncUser', 5];
    return $events;
  }

  public function syncUser(SamlauthUserSyncEvent $syncEvent) {
    $account = $syncEvent->getAccount();
    $attributes = $syncEvent->getAttributes();
    if ($syncEvent->isFirstLogin()) {
      // $syncEvent->markAccountChanged();
    }
  }

}
