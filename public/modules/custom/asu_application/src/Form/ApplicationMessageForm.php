<?php

declare(strict_types=1);

namespace Drupal\asu_application\Form;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\UserRequest;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\asu_application\ApplicationMessageManager;
use Drupal\asu_application\Entity\Application;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for sending messages about an application to the salesperson.
 */
final class ApplicationMessageForm extends FormBase {

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
    private readonly MailManagerInterface $mailManager,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly AccountProxyInterface $currentUser,
    private readonly BackendApi $backendApi,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('asu_application.message_manager'),
      $container->get('plugin.manager.mail'),
      $container->get('date.formatter'),
      $container->get('current_user'),
      $container->get('asu_api.backendapi'),
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
    $projectLabel = $this->messageManager->getProjectLabel($asu_application);
    $thread = $this->messageManager->loadThread((int) $asu_application->id());

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

    if ($thread === []) {
      $form['thread_empty'] = [
        '#type' => 'item',
        '#title' => $this->t('Conversation'),
        '#markup' => $this->t('No messages yet.'),
      ];
    }
    else {
      $items = [];
      foreach ($thread as $message) {
        $senderRole = (string) $message->get('sender_role')->value;
        $senderLabel = $senderRole === 'sales'
          ? (string) $this->t('Sales agent')
          : (string) $this->t('You');
        $created = (int) ($message->get('created')->value ?? 0);
        $body = nl2br(Html::escape((string) $message->get('body')->value));
        $timestamp = $created > 0 ? $this->dateFormatter->format($created, 'custom', 'd.m.Y H:i') : '';

        $items[] = Markup::create(
          '<div class="application-message-form__message">'
          . '<p><strong>' . Html::escape($senderLabel) . '</strong>'
          . ($timestamp !== '' ? ' <span>' . Html::escape($timestamp) . '</span>' : '')
          . '</p><div>' . $body . '</div></div>'
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
    $salesperson = $this->messageManager->resolveSalesperson($this->application);
    $recipientMail = $this->messageManager->resolveRecipientMail($this->application);
    $projectLabel = $this->messageManager->getProjectLabel($this->application);
    $recipientLangcode = ($salesperson && method_exists($salesperson, 'getPreferredLangcode') && $salesperson->getPreferredLangcode() !== '')
      ? $salesperson->getPreferredLangcode()
      : ($this->currentUser->getPreferredLangcode() ?: 'fi');

    $this->messageManager->createMessage(
      (int) $this->application->id(),
      $this->messageManager->getProjectId($this->application),
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
      $lines = array_merge($lines, [
        '',
        (string) $this->t('Message content:', [], ['langcode' => $recipientLangcode]),
        '',
        $body,
        '',
      ]);

      if ($salesThreadUrl !== '') {
        $lines[] = (string) $this->t('Open chat: @url', ['@url' => $salesThreadUrl], ['langcode' => $recipientLangcode]);
      }

      $lines = array_merge($lines, [
        (string) $this->t('This is an automated message. Please do not reply to this email.', [], ['langcode' => $recipientLangcode]),
      ]);

      $this->mailManager->mail('asu_application', 'application_message_notification', $recipientMail, $recipientLangcode, [
        'subject' => $subject,
        'message_lines' => $lines,
      ], NULL, TRUE);
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
    $uid = (int) $this->currentUser->id();
    if ($uid > 0) {
      $user = User::load($uid);
      if ($user) {
        $fullName = $this->buildFullName(
          $user->hasField('first_name') && !$user->get('first_name')->isEmpty() ? (string) $user->get('first_name')->value : '',
          $user->hasField('last_name') && !$user->get('last_name')->isEmpty() ? (string) $user->get('last_name')->value : '',
        );

        if ($fullName !== '') {
          return $fullName;
        }

        try {
          if ($user->hasField('field_backend_profile') && !$user->get('field_backend_profile')->isEmpty()) {
            $request = new UserRequest($user);
            $request->setSender($user);
            $response = $this->backendApi->send($request);
            $userInformation = $response->getUserInformation();

            $backendFullName = $this->buildFullName(
              (string) ($userInformation['first_name'] ?? ''),
              (string) ($userInformation['last_name'] ?? ''),
            );
            if ($backendFullName !== '') {
              return $backendFullName;
            }
          }
        }
        catch (\Exception $e) {
          // Fallback continues below.
        }

        if ($user->hasField('field_full_name') && !$user->get('field_full_name')->isEmpty()) {
          $storedFullName = trim((string) $user->get('field_full_name')->value);
          if ($storedFullName !== '') {
            return $storedFullName;
          }
        }

        if ($user->getDisplayName() !== '') {
          return $user->getDisplayName();
        }
      }
    }

    return $this->currentUser->getDisplayName() ?: (string) $this->t('Customer');
  }

  /**
   * Builds a normalized full name from first and last name parts.
   */
  private function buildFullName(string $firstName, string $lastName): string {
    return trim(trim($firstName) . ' ' . trim($lastName));
  }

  /**
   * Builds absolute URL using configured public base URL when available.
   */
  private function buildAbsoluteUrl(string $path): string {
    $baseUrl = getenv('ASU_ASUNTOTUOTANTO_URL');
    if ($baseUrl) {
      return rtrim($baseUrl, '/') . $path;
    }

    $request = \Drupal::request();
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

}
