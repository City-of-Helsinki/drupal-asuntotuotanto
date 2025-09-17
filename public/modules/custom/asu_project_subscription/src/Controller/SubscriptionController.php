<?php

namespace Drupal\asu_project_subscription\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\asu_project_subscription\Entity\ProjectSubscription;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends ControllerBase {

  public function confirm($token) {
    $subs = \Drupal::entityTypeManager()
      ->getStorage('asu_project_subscription')
      ->loadByProperties(['confirm_token' => $token]);

    if ($subs) {
      $sub = reset($subs);
      if (!$sub->get('is_confirmed')->value && !$sub->get('unsubscribed_at')->value) {
        $sub->set('is_confirmed', TRUE)->save();
        $text = $this->t('Your subscription has been confirmed.');
      } else {
        $text = $this->t('This subscription is already confirmed or inactive.');
      }
      return ['#markup' => $text];
    }
    return new Response('Invalid or expired token', 404);
  }

  public function unsubscribe($token) {
    $subs = \Drupal::entityTypeManager()
      ->getStorage('asu_project_subscription')
      ->loadByProperties(['unsubscribe_token' => $token]);

    if ($subs) {
      $sub = reset($subs);
      $sub->set('unsubscribed_at', time());
      $sub->save();
      return [
        '#markup' => $this->t('You have been unsubscribed.'),
      ];
    }
    return new Response('Invalid or expired token', 404);
  }

}
