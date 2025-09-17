<?php

namespace Drupal\asu_project_subscription\Plugin\QueueWorker;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @QueueWorker(
 *   id = "asu_project_subscription_notify",
 *   title = @Translation("ASU project subscription notifier"),
 *   cron = {"time" = 30}
 * )
 */
class ProjectSubscriptionNotifier extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  protected $mailManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, MailManagerInterface $mail_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->mailManager = $mail_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.mail')
    );
  }

  public function processItem($data) {
    $project_nid = (int) ($data['project_nid'] ?? 0);
    $new_state   = (string) ($data['new_state'] ?? '');
    $mail_key    = (string) ($data['mail_key'] ?? '');

    if (!$project_nid || !$new_state || !$mail_key) {
      return;
    }

     $query = $this->etm->getStorage('asu_project_subscription')->getQuery()
      ->accessCheck(FALSE)
      ->condition('project', $project_nid)
      ->condition('is_confirmed', 1)
      ->condition('unsubscribed_at', NULL, 'IS NULL');

    $or = $query->orConditionGroup()
      ->condition('last_notified_state', $new_state, '!=')
      ->condition('last_notified_state', NULL, 'IS NULL');
    $query->condition($or);

    $ids = $query->execute();
    if (!$ids) {
      $this->loggerFactory->get('asu_ps')->notice('No recipients for node @nid and state @s', ['@nid' => $project_nid, '@s' => $new_state]);
      return;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('asu_project_subscription');
    $subs = $storage->loadMultiple($ids);

    $project = \Drupal::entityTypeManager()->getStorage('node')->load($project_nid);
    $project_title = $project ? $project->label() : t('project');
    $project_url   = $project ? Url::fromRoute('entity.node.canonical', ['node' => $project_nid], ['absolute' => TRUE])->toString() : '';

    foreach ($subs as $sub) {
      $last = (string) ($sub->get('last_notified_state')->value ?? '');
      if ($last === $new_state) {
        continue;
      }

      $to = $sub->get('email')->value;
      $langcode = $sub->get('langcode')->value ?: \Drupal::languageManager()->getDefaultLanguage()->getId();

      $params = [
        'subject' => t('Update for @project', ['@project' => $project_title], ['langcode' => $langcode]),
        'message' => [
          '#markup' => t('Project "@title" status changed to: @state. See details: @url', [
            '@title' => $project_title,
            '@state' => $new_state,
            '@url'   => $project_url,
          ], ['langcode' => $langcode]),
        ],
      ];

      $result = $this->mailManager->mail(
        'asu_project_subscription',
        $mail_key,
        $to,
        $langcode,
        $params
      );

      if (!empty($result['result'])) {
        $sub->set('last_notified_state', $new_state);
        $sub->save();
      }
    }
  }
}
