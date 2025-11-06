<?php

namespace Drupal\asu_project_subscription\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for handling subscription confirm/unsubscribe flows.
 *
 * @category Drupal
 * @package Asu_Project_Subscription
 * @author Helsinki Dev Team <dev@hel.fi>
 * @license https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @version Release: 1.0.0
 * @link https://www.drupal.org
 */
class SubscriptionController extends ControllerBase {

  /**
   * Confirm a subscription by token and redirect.
   *
   * @param string $token
   *   The confirmation token received by email.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   */
  public function confirm($token) {
    $storage = \Drupal::entityTypeManager()->getStorage('asu_project_subscription');
    $subs = $storage->loadByProperties(['confirm_token' => $token]);

    if ($subs) {
      /**
       * A loaded subscription entity.
       *
       * @var \Drupal\asu_project_subscription\Entity\ProjectSubscription $sub
       */
      $sub = reset($subs);
      $nid = (int) ($sub->get('project')->target_id ?? $sub->get('project')->value);

      $ps = 'confirmed';
      if (!$sub->get('is_confirmed')->value && !$sub->get('unsubscribed_at')->value) {
        $sub->set('is_confirmed', TRUE);
        $sub->set('unsubscribed_at', NULL);
        $sub->save();
        $ps = 'confirmed';
      }
      elseif ($sub->get('unsubscribed_at')->value) {
        $ps = 'confirm_failed';
      }
      else {
        $ps = 'already_confirmed';
      }

      if ($nid) {
        $url = Url::fromRoute(
          'entity.node.canonical',
          ['node' => $nid],
          [
            'absolute' => TRUE,
            'query' => ['ps' => $ps],
            'fragment' => 'liitteet',
          ]
        )->toString();

        return new RedirectResponse($url);
      }
    }

    $url = Url::fromRoute(
      '<front>',
      [],
      [
        'absolute' => TRUE,
        'query' => ['ps' => 'confirm_failed'],
      ]
    )->toString();

    return new RedirectResponse($url);
  }

  /**
   * Unsubscribe by token and redirect to the project/front page.
   *
   * @param string $token
   *   The unsubscribe token received by email.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   */
  public function unsubscribe($token) {
    $storage = \Drupal::entityTypeManager()->getStorage('asu_project_subscription');
    $subs = $storage->loadByProperties(['unsubscribe_token' => $token]);

    if ($subs) {
      /**
       * A loaded subscription entity.
       *
       * @var \Drupal\asu_project_subscription\Entity\ProjectSubscription $sub
       */
      $sub = reset($subs);
      $nid = (int) ($sub->get('project')->target_id ?? $sub->get('project')->value);

      $sub->set('unsubscribed_at', \Drupal::time()->getRequestTime());
      $sub->save();

      if ($nid) {
        $url = Url::fromRoute(
          'entity.node.canonical',
          ['node' => $nid],
          [
            'absolute' => TRUE,
            'query' => ['ps' => 'unsubscribed'],
            'fragment' => 'liitteet',
          ]
        )->toString();

        return new RedirectResponse($url);
      }
    }

    $url = Url::fromRoute(
      '<front>',
      [],
      [
        'absolute' => TRUE,
        'query' => ['ps' => 'unsub_failed'],
      ]
    )->toString();

    return new RedirectResponse($url);
  }

  /**
   * Render subscription table for a project node.
   *
   * @param int $node
   *   The project node ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   Render array for the subscription list page.
   */
  public function view($node, Request $request) {
    $header = [
      'email' => [
        'data' => $this->t('Email'),
        'field' => 'aps.email',
        'sort' => 'asc',
      ],
      'is_confirmed' => [
        'data' => $this->t('Confirmed'),
        'field' => 'aps.is_confirmed',
      ],
      'created' => [
        'data' => $this->t('Subscribed'),
        'field' => 'aps.created',
      ],
      'unsubscribed_at' => [
        'data' => $this->t('Unsubscribed'),
        'field' => 'aps.unsubscribed_at',
      ],
      'operations' => $this->t('Operations'),
    ];

    $connection = Database::getConnection();

    $query = $connection->select('asu_project_subscription', 'aps')
      ->fields('aps', ['id', 'email', 'is_confirmed', 'created', 'unsubscribed_at'])
      ->condition('project', $node);

    $query = $query
      ->extend('Drupal\Core\Database\Query\TableSortExtender')
      ->orderByHeader($header)
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(25);

    $result = $query->execute();

    /**
     * Date formatter service.
     *
     * @var \Drupal\Core\Datetime\DateFormatterInterface $df
     */
    $df = \Drupal::service('date.formatter');

    $rows = [];
    foreach ($result as $record) {
      $ops = [];

      if (empty($record->unsubscribed_at)) {
        $unsubscribe_url = Url::fromRoute(
          'asu_project_subscription.unsubscribe_manual',
          ['subscription' => $record->id]
        );
        $ops[] = Link::fromTextAndUrl($this->t('Unsubscribe'), $unsubscribe_url)->toRenderable();
      }

      $rows[] = [
        'email' => [
          'data' => ['#plain_text' => $record->email],
        ],
        'is_confirmed' => [
          'data' => [
            '#plain_text' => $record->is_confirmed ? $this->t('Yes') : $this->t('No'),
          ],
        ],
        'created' => [
          'data' => [
            '#plain_text' => $record->created ? $df->format($record->created, 'short') : '-',
          ],
        ],
        'unsubscribed_at' => [
          'data' => [
            '#plain_text' => $record->unsubscribed_at ? $df->format($record->unsubscribed_at, 'short') : '-',
          ],
        ],
        'operations' => [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{{ items|render }}',
            '#context' => ['items' => $ops],
          ],
        ],
      ];
    }

    $build['title'] = [
      '#type' => 'container',
      'h1' => [
        '#markup' => '<h1>' . $this->t('Subscriptions') . '</h1>',
      ],
      '#attributes' => ['class' => ['application-form__header']],
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No subscriptions found for this project.'),
      '#attributes' => [
        'class' => [
          'hds-table',
          'hds-table--compact',
          'hds-table--zebra',
        ],
      ],
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    $build['back'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['application-form__actions']],
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Back'),
        '#url' => Url::fromRoute('entity.node.canonical', ['node' => $node]),
        '#attributes' => [
          'class' => ['hds-button', 'hds-button--secondary'],
        ],
      ],
    ];

    $build['#attached']['library'][] = 'asuntotuotanto/global';

    return $build;
  }

  /**
   * Manually unsubscribe a subscriber from the list.
   *
   * @param int $subscription
   *   SubId.
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   Request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   */
  public function unsubscribeManual($subscription, ?Request $request = NULL) {
    $connection = Database::getConnection();

    $record = $connection->select('asu_project_subscription', 'aps')
      ->fields('aps', ['id', 'project', 'unsubscribed_at'])
      ->condition('id', $subscription)
      ->execute()
      ->fetchObject();

    if (!$record) {
      $this->messenger()->addError($this->t('Subscription not found.'));
      return $this->redirect('<front>');
    }

    if (!empty($record->unsubscribed_at)) {
      $this->messenger()->addWarning($this->t('This subscription is already unsubscribed.'));
      return $this->redirect('asu_project_subscription.project_subscriptions', ['node' => $record->project]);
    }

    $connection->update('asu_project_subscription')
      ->fields(['unsubscribed_at' => \Drupal::time()->getRequestTime()])
      ->condition('id', $subscription)
      ->execute();

    $this->messenger()->addStatus($this->t('The subscriber has been unsubscribed.'));

    return $this->redirect('asu_project_subscription.project_subscriptions', ['node' => $record->project]);
  }

  /**
   * Page title callback.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A page title.
   */
  public function title() {
    return $this->t('Subscriptions');
  }

}
