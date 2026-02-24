<?php

namespace Drupal\asu_project_subscription\Plugin\QueueWorker;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes ASU project subscription notifications.
 *
 * @QueueWorker(
 *   id = "asu_project_subscription_notify",
 *   title = @Translation("ASU project subscription notifier"),
 *   cron = {"time" = 30}
 * )
 *
 * @category Drupal
 * @package Asu_Project_Subscription
 * @license https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link https://www.drupal.org
 */
class ProjectSubscriptionNotifier extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The logger factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs the queue worker.
   *
   * @param array $configuration
   *   Plugin config.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   Mail manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, MailManagerInterface $mail_manager, LanguageManagerInterface $language_manager, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('language_manager'),
      $container->get('logger.factory')
    );
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * Processes a single queue item.
   *
   * @param array $data
   *   Queue item payload.
   */
  public function processItem($data): void {
    $project_nid = (int) ($data['project_nid'] ?? 0);
    $new_state = (string) ($data['new_state'] ?? '');
    $mail_key = (string) ($data['mail_key'] ?? '');

    if (!$project_nid || $new_state === '' || $mail_key === '') {
      $this->loggerFactory->get('asu_ps')->notice(
      'Skip item: missing nid/state/key. Data=@data',
      [
        '@data' => json_encode($data, JSON_UNESCAPED_UNICODE),
      ]
      );
      return;
    }

    $node = $this->entityTypeManager->getStorage('node')->load($project_nid);
    $title = $node ? $node->label() : '';
    $escTitle = Html::escape($title);

    try {
      $project_url = Url::fromRoute('entity.node.canonical', ['node' => $project_nid], ['absolute' => TRUE])->toString();
    }
    catch (\Throwable $e) {
      $project_url = Url::fromUri('internal:/node/' . $project_nid, ['absolute' => TRUE])->toString();
    }

    $storage = $this->entityTypeManager->getStorage('asu_project_subscription');

    $query = \Drupal::entityQuery('asu_project_subscription')
      ->accessCheck(FALSE)
      ->condition('project', $project_nid)
      ->condition('is_confirmed', 1)
      ->condition('unsubscribed_at', NULL, 'IS NULL');

    $or = $query->orConditionGroup()
      ->condition('last_notified_state', NULL, 'IS NULL')
      ->condition('last_notified_state', $new_state, '<>');

    $ids = $query->condition($or)->execute();

    if (!$ids) {
      $this->loggerFactory->get('asu_ps')->notice(
        'No recipients for nid @nid and state @state',
        [
          '@nid' => $project_nid,
          '@state' => $new_state,
        ]
      );

      return;
    }

    $subs = $storage->loadMultiple($ids);

    foreach ($subs as $sub) {
      /**
       * @var \Drupal\asu_project_subscription\Entity\ProjectSubscription $sub
       * */
      $to = (string) $sub->get('email')->value;
      if (!$to) {
        continue;
      }

      $unsub_token = (string) $sub->get('unsubscribe_token')->value;
      $unsub_url = Url::fromRoute('asu_project_subscription.unsubscribe', ['token' => $unsub_token], ['absolute' => TRUE])->toString();

      $langcode = $sub->get('langcode')->value ?: 'fi';

      $subject = ($new_state === 'Myynnissä')
        ? $this->t('Sales started — @title', ['@title' => $title], ['langcode' => $langcode])
        : $this->t('Project update — @title', ['@title' => $title], ['langcode' => $langcode]);

      $lines = [];
      if ($new_state === 'Myynnissä') {
        $lines[] = $this->t('Good news! The project has moved to sales.', [], ['langcode' => $langcode]);
      }
      else {
        $lines[] = $this->t('The project status has been updated.', [], ['langcode' => $langcode]);
      }

      $lines[] = '<br />';
      if ($new_state === 'Myynnissä') {
        $lines[] = (string) $this->t('Application period has started.', [], ['langcode' => $langcode]);
      }
      elseif ($new_state === 'Ennakkomarkkinoinnissa') {
        $lines[] = (string) $this->t('Pre-marketing', [], ['langcode' => $langcode]);
      }
      else {
        $lines[] = (string) $this->t('New status: @state', ['@state' => $new_state], ['langcode' => $langcode]);
      }

      if ($title) {
        $lines[] = (string) $this->t('Project: @title', ['@title' => $escTitle], ['langcode' => $langcode]);
      }

      $lines[] = '<br />';

      $open_label = (string) $this->t('Open project page', [], ['langcode' => $langcode]);
      $lines[] = '<a href="' . Html::escape($project_url) . '">' . Html::escape($open_label) . '</a>';

      $lines[] = '<hr />';

      $unsub_label = (string) $this->t('Unsubscribe', [], ['langcode' => $langcode]);
      $lines[] = '<a href="' . Html::escape($unsub_url) . '">' . Html::escape($unsub_label) . '</a>';

      $message_markup = implode('<br/>', array_map(
        static function ($x) {
          return (string) $x;
        },
        $lines
      ));

      $params = [
        'subject' => $subject,
        'message' => [
          '#markup' => $message_markup,
        ],
      ];

      $result = $this->mailManager->mail('asu_project_subscription', $mail_key, $to, $langcode, $params, NULL, TRUE);

      if (!empty($result['result'])) {
        $sub->set('last_notified_state', $new_state);
        $sub->save();
      }
    }
  }

}
