<?php

declare(strict_types=1);

namespace Drupal\asu_application\Form;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\asu_application\ApplicationAttachmentManager;
use Drupal\asu_application\ApplicationMessageManager;
use Drupal\asu_application\Entity\Application;
use Drupal\asu_application\Notification\ApplicationProjectResolverTrait;
use Drupal\asu_application\Notification\SenderNameResolverTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form for sending messages about an application to the salesperson.
 */
final class ApplicationMessageForm extends FormBase {
  use ApplicationProjectResolverTrait;
  use SenderNameResolverTrait;

  /**
   * The current application.
   *
   * @var \Drupal\asu_application\Entity\Application|null
   */
  private ?Application $application = NULL;

  /**
   * Constructs the form.
   */
  public function __construct(
    private readonly ApplicationMessageManager $messageManager,
    private readonly ApplicationAttachmentManager $attachmentManager,
    private readonly MailManagerInterface $mailManager,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly AccountProxyInterface $currentUser,
    private readonly BackendApi $backendApi,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RequestStack $httpRequestStack,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
          $container->get('asu_application.message_manager'),
          $container->get('asu_application.attachment_manager'),
          $container->get('plugin.manager.mail'),
          $container->get('date.formatter'),
          $container->get('current_user'),
          $container->get('asu_api.backendapi'),
          $container->get('entity_type.manager'),
          $container->get('request_stack'),
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'asu_application_message_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Application $asu_application = NULL): array {
    if (!$asu_application) {
      throw new \InvalidArgumentException('Application is required.');
    }

    $this->application = $asu_application;
    $projectLabel = $this->resolveApplicationProjectLabel($asu_application);
    $thread = $this->messageManager->loadThread((int) $asu_application->id());
    $messageIds = array_map(static fn ($message): int => (int) $message->id(), $thread);
    $attachmentsByMessageId = $this->attachmentManager->getAttachmentsByMessageIds($messageIds);
    $currentUid = (int) $this->currentUser->id();
    $viewerRole = $this->messageManager->resolveViewerRole($currentUid);
    $lastReadAt = $currentUid > 0
        ? $this->messageManager->getThreadLastRead($currentUid, (int) $asu_application->id())
        : 0;

    if ($currentUid > 0) {
      $this->messageManager->markThreadRead($currentUid, (int) $asu_application->id());
    }

    $form['intro'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['application-message-form__intro'],
      ],
    ];
    $form['intro']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Contact sales agent'),
      '#attributes' => [
        'class' => ['application-message-form__title'],
      ],
    ];
    $form['intro']['description'] = [
      '#markup' => $projectLabel !== ''
        ? $this->t('Send a message about @project. Replies saved to this thread can be shown here later.', ['@project' => $projectLabel])
        : $this->t('Send a message to the sales agent assigned to this application.'),
    ];

    $form['back_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to application'),
      '#url' => Url::fromUri('internal:/application/' . $asu_application->id()),
      '#attributes' => [
        'class' => ['application-message-form__back-link'],
      ],
    ];

    $this->buildThreadSection($form, $thread, $attachmentsByMessageId, $lastReadAt, $viewerRole);

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
      '#rows' => 6,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send message'),
      '#button_type' => 'primary',
    ];

    $form['#attached']['library'][] = 'asu_application/application_results';
    $form['#attached']['library'][] = 'asu_application/message_badges';

    $form['#cache'] = ['max-age' => 0];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $message = trim((string) $form_state->getValue('message'));
    if ($message === '') {
      $form_state->setErrorByName('message', $this->t('Message cannot be empty.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->application) {
      return;
    }

    $body = trim((string) $form_state->getValue('message'));
    $salesperson = $this->resolveApplicationSalesperson($this->application);
    $recipientMail = $this->resolveApplicationRecipientMail($this->application, $salesperson);
    $projectLabel = $this->resolveApplicationProjectLabel($this->application);
    $recipientLangcode = ($salesperson && method_exists($salesperson, 'getPreferredLangcode') && $salesperson->getPreferredLangcode() !== '')
        ? $salesperson->getPreferredLangcode()
        : ($this->currentUser->getPreferredLangcode() ?: 'fi');

    $this->messageManager->createMessage(
          (int) $this->application->id(),
          (int) $this->application->getProjectId(),
          $body,
          'customer',
          (int) $this->currentUser->id(),
          $salesperson ? (int) $salesperson->id() : NULL,
          $recipientMail,
      );

    if ($recipientMail !== '') {
      $subject = (string) $this->t(
            'New message about application @id',
            ['@id' => (string) $this->application->id()],
            ['langcode' => $recipientLangcode],
        );
      $salesThreadUrl = $this->getSalesThreadUrl();
      $senderName = $this->resolveSenderName();
      $lines = [
        (string) $this->t('You have received a new message in the application service.', [], ['langcode' => $recipientLangcode]),
        '',
        (string) $this->t('Project: @project', ['@project' => $projectLabel !== '' ? $projectLabel : '-'], ['langcode' => $recipientLangcode]),
        (string) $this->t('Application ID: @id', ['@id' => (string) $this->application->id()], ['langcode' => $recipientLangcode]),
        (string) $this->t('Sender: @name', ['@name' => $senderName], ['langcode' => $recipientLangcode]),
      ];
      $lines = array_merge(
            $lines, [
              '',
              (string) $this->t('Message content:', [], ['langcode' => $recipientLangcode]),
              '',
              $body,
              '',
            ]
        );

      if ($salesThreadUrl !== '') {
        $lines[] = (string) $this->t('Open chat: @url', ['@url' => $salesThreadUrl], ['langcode' => $recipientLangcode]);
      }

      $lines = array_merge(
            $lines, [
              (string) $this->t('This is an automated message. Please do not reply to this email.', [], ['langcode' => $recipientLangcode]),
            ]
        );

      $this->mailManager->mail(
            'asu_application', 'application_message_notification', $recipientMail, $recipientLangcode, [
              'subject' => $subject,
              'message_lines' => $lines,
            ], NULL, TRUE
        );
    }
    else {
      $this->messenger()->addWarning($this->t('The message was saved, but no salesperson email address was found.'));
    }

    $this->messenger()->addStatus($this->t('Your message has been sent.'));
    $form_state->setRedirect('asu_application.messages', ['asu_application' => $this->application->id()]);
  }

  /**
   * Resolves sender name for email notifications.
   */
  private function resolveSenderName(): string {
    return $this->resolveNotificationSenderName(
          $this->currentUser,
          $this->backendApi,
          $this->entityTypeManager,
          (string) $this->t('Customer'),
      );
  }

  /**
   * Builds absolute URL using configured public base URL when available.
   */
  private function buildAbsoluteUrl(string $path): string {
    $baseUrl = getenv('ASU_ASUNTOTUOTANTO_URL');
    if ($baseUrl) {
      return rtrim($baseUrl, '/') . $path;
    }

    $request = $this->httpRequestStack->getCurrentRequest();
    $host = $request ? $request->getSchemeAndHttpHost() : '';

    return $host . $path;
  }

  /**
   * Returns salesperson-facing message thread URL in Drupal.
   */
  private function getSalesThreadUrl(): string {
    if (!$this->application) {
      return '';
    }

    return $this->buildAbsoluteUrl('/application/' . $this->application->id() . '/messages');
  }

  /**
   * Resolves display label for a thread message sender.
   */
  private function resolveMessageSenderName($message, array &$senderNamesByUid): string {
    $senderRole = (string) $message->get('sender_role')->value;
    $senderUid = $message->get('sender_uid')->isEmpty() ? 0 : (int) $message->get('sender_uid')->target_id;
    $salespersonUid = $message->get('salesperson_uid')->isEmpty() ? 0 : (int) $message->get('salesperson_uid')->target_id;

    if ($senderRole === 'sales' && $salespersonUid > 0) {
      $senderUid = $salespersonUid;
    }

    if ($senderUid > 0) {
      if (!array_key_exists($senderUid, $senderNamesByUid)) {
        $sender = $this->entityTypeManager->getStorage('user')->load($senderUid);
        $senderNamesByUid[$senderUid] = $sender && $sender->getDisplayName() !== ''
                ? $sender->getDisplayName()
                : '';
      }

      if ($senderNamesByUid[$senderUid] !== '') {
        if ($senderRole === 'sales' && $senderNamesByUid[$senderUid] === 'rest_client' && $this->application) {
          $salesperson = $this->resolveApplicationSalesperson($this->application);
          if ($salesperson && $salesperson->getDisplayName() !== '') {
            return $salesperson->getDisplayName();
          }
        }
        return $senderNamesByUid[$senderUid];
      }
    }

    return $senderRole === 'sales'
        ? (string) $this->t('Sales agent')
        : (string) $this->t('Customer');
  }

  /**
   * Returns whether a sender role is incoming for the current viewer side.
   */
  private function isIncomingMessageForViewer(string $senderRole, string $viewerRole): bool {
    if ($viewerRole === 'sales') {
      return $senderRole === 'customer';
    }

    return $senderRole === 'sales';
  }

  /**
   * Provides entity manager for project resolver trait.
   */
  protected function getEntityTypeManagerForProjectResolver(): EntityTypeManagerInterface {
    return $this->entityTypeManager;
  }

  /**
   * Adds conversation section to the form.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param array<int, mixed> $thread
   *   Thread messages.
   * @param array<int, array<int, array<string, mixed>>> $attachmentsByMessageId
   *   Attachments indexed by message id.
   * @param int $lastReadAt
   *   Viewer last-read timestamp.
   * @param string $viewerRole
   *   Viewer role side.
   */
  private function buildThreadSection(array &$form, array $thread, array $attachmentsByMessageId, int $lastReadAt, string $viewerRole): void {
    if ($thread === []) {
      $form['thread_empty'] = [
        '#type' => 'item',
        '#title' => $this->t('Conversation'),
        '#markup' => $this->t('No messages yet.'),
      ];
      return;
    }

    $items = [];
    $senderNamesByUid = [];
    foreach ($thread as $message) {
      $items[] = $this->buildThreadItemMarkup(
            $message,
            $senderNamesByUid,
            $attachmentsByMessageId[(int) $message->id()] ?? [],
            $lastReadAt,
            $viewerRole,
        );
    }

    $form['thread'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Conversation'),
      '#items' => $items,
      '#attributes' => [
        'class' => ['application-message-form__thread'],
      ],
    ];
  }

  /**
   * Builds one message item HTML for the thread list.
   */
  private function buildThreadItemMarkup($message, array &$senderNamesByUid, array $attachments, int $lastReadAt, string $viewerRole): Markup {
    $senderLabel = $this->resolveMessageSenderName($message, $senderNamesByUid);
    $created = (int) ($message->get('created')->value ?? 0);
    $senderRole = (string) ($message->get('sender_role')->value ?? 'customer');
    $isUnread = $created > $lastReadAt
        && $this->isIncomingMessageForViewer($senderRole, $viewerRole);
    $isOutgoing = !$this->isIncomingMessageForViewer($senderRole, $viewerRole);
    $body = nl2br(Html::escape((string) $message->get('body')->value));
    $timestamp = $created > 0 ? $this->dateFormatter->format($created, 'custom', 'd.m.Y H:i') : '';
    $messageClasses = 'application-message-form__message'
        . ($isUnread ? ' application-message-form__message--new' : '')
        . ($isOutgoing ? ' application-message-form__message--outgoing' : ' application-message-form__message--incoming');
    $newLabel = $isUnread
        ? '<span class="application-message-form__new-label">' . Html::escape((string) $this->t('New')) . '</span>'
        : '';
    $attachmentsMarkup = '';
    if ($attachments !== []) {
      $attachmentItems = [];
      foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
          continue;
        }

        $name = Html::escape((string) ($attachment['display_name'] ?? 'attachment'));
        $openUrl = (string) ($attachment['open_url'] ?? '');
        $downloadUrl = (string) ($attachment['download_url'] ?? '');

        if (($openUrl === '' || !str_starts_with($openUrl, '/')) && str_starts_with($downloadUrl, '/')) {
          $openUrl = str_replace('/download', '/open', $downloadUrl);
        }

        if ($downloadUrl === '' || !str_starts_with($downloadUrl, '/') || $openUrl === '' || !str_starts_with($openUrl, '/')) {
          continue;
        }

        $size = $this->formatAttachmentSize((int) ($attachment['size_bytes'] ?? 0));
        $openHref = Html::escape($openUrl);
        $downloadHref = Html::escape($downloadUrl);
        $attachmentItems[] = '<li class="application-message-form__attachment-item">'
                . '<span class="application-message-form__attachment-file" title="' . $name . '">'
                . '<span class="application-message-form__attachment-name">' . $name . '</span>'
                . '<span class="application-message-form__attachment-size"> (' . Html::escape($size) . ')</span>'
                . '</span>'
                . '<span class="application-message-form__attachment-actions">'
                . '<a class="hds-button hds-button--secondary" href="' . $openHref . '">' . Html::escape((string) $this->t('Open')) . '</a>'
                . '<a class="hds-button hds-button--secondary" href="' . $downloadHref . '">' . Html::escape((string) $this->t('Download')) . '</a>'
                . '</span>'
                . '</li>';
      }

      if ($attachmentItems !== []) {
        $attachmentsMarkup = '<div class="application-message-form__attachments">'
                . '<strong>' . Html::escape((string) $this->t('Files in message (@count)', ['@count' => count($attachmentItems)])) . '</strong>'
                . '<ul>' . implode('', $attachmentItems) . '</ul>'
                . '</div>';
      }
    }

    return Markup::create(
          '<div class="' . $messageClasses . '">'
          . '<div class="application-message-form__message-header">'
          . '<strong class="application-message-form__sender">' . Html::escape($senderLabel) . '</strong>'
          . ($timestamp !== '' ? ' <span class="application-message-form__message-meta">' . Html::escape($timestamp) . '</span>' : '')
          . $newLabel
          . '</div>'
          . '<div class="application-message-form__message-body">' . $body . '</div>'
          . $attachmentsMarkup
          . '</div>'
      );
  }

  /**
   * Formats attachment size for compact display.
   */
  private function formatAttachmentSize(int $sizeBytes): string {
    if ($sizeBytes <= 0) {
      return '0B';
    }

    $units = ['B', 'KB', 'MB', 'GB'];
    $value = (float) $sizeBytes;
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
      $value /= 1024;
      $unitIndex++;
    }

    if ($unitIndex === 0) {
      return (string) ((int) $value) . $units[$unitIndex];
    }

    return number_format($value, 1, '.', '') . $units[$unitIndex];
  }

}
