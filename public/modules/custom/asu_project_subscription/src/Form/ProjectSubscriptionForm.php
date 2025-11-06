<?php

namespace Drupal\asu_project_subscription\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\asu_project_subscription\Entity\ProjectSubscription;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the Project Subscription form.
 *
 * PHP version 8.1
 *
 * @category Drupal
 * @package Asu_Project_Subscription
 * @license https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later License
 * @link https://www.drupal.org
 */
class ProjectSubscriptionForm extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new ProjectSubscriptionForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'asu_project_subscription_form';
  }

  /**
   * Builds the project subscription form.
   *
   * @param array $form
   *   Form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param \Drupal\node\NodeInterface|null $project
   *   Project.
   *
   * @return array
   *   The render array of the form.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $project = NULL,
  ) {
    $form['project'] = [
      '#type' => 'hidden',
      '#value' => ($project instanceof NodeInterface) ? (int) $project->id() : '',
    ];

    $project_id = ($project instanceof NodeInterface) ? (int) $project->id() : 0;

    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $account = \Drupal::currentUser();
    $user_email = NULL;
    if ($account->isAuthenticated()) {
      $user_email = $account->getEmail() ? mb_strtolower($account->getEmail()) : NULL;
    }

    $form['hp_website'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Website'),
      '#attributes' => ['autocomplete' => 'off'],
      '#access' => FALSE,
    ];

    $has_active_subscription = FALSE;
    $existing_sub_id = NULL;

    if ($user_email) {
      $normalized_email = mb_strtolower($user_email);
      // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
      $ids = \Drupal::entityQuery('asu_project_subscription')
        ->accessCheck(FALSE)
        ->condition('project', $project_id)
        ->condition('email', $normalized_email)
        ->condition('is_confirmed', 1)
        ->condition('unsubscribed_at', NULL, 'IS NULL')
        ->range(0, 1)
        ->execute();

      if ($ids) {
        $existing_sub_id = reset($ids);
        $has_active_subscription = TRUE;
      }
    }

    $form['mode'] = [
      '#type' => 'hidden',
      '#value' => $has_active_subscription ? 'unsubscribe' : 'subscribe',
    ];

    if ($has_active_subscription) {
      $form['status'] = [
        '#type' => 'item',
        '#markup' => $this->t('You are subscribed to updates for this property.'),
        '#attributes' => ['class' => ['asu-ps-status']],
      ];
      $form['existing_sub_id'] = [
        '#type' => 'hidden',
        '#value' => $existing_sub_id,
      ];
    }
    else {
      $placeholder = $user_email ?: (string) $this->t('Your email address');

      $form['email'] = [
        '#type' => 'email',
        '#title' => $this->t('Your email address'),
        '#title_display' => 'invisible',
        '#required' => TRUE,
        '#size' => 32,
        '#attributes' => [
          'placeholder' => $placeholder,
          'autocomplete' => 'email',
          'inputmode' => 'email',
          'aria-label' => (string) $this->t('Your email address'),
        ],
        '#default_value' => $user_email,
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $has_active_subscription ? $this->t('Unsubscribe') : $this->t('Subscribe'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Form submit handler for the project subscription form.
   *
   * @param array $form
   *   Form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return void
   *   Nothing is returned.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mode = $form_state->getValue('mode');
    $project_id = (int) $form_state->getValue('project');

    if (!empty($form_state->getValue('hp_website'))) {
      $this->messenger()->addError($this->t('Form validation failed.'));
      return;
    }

    if ($mode === 'unsubscribe') {
      $sub_id = $form_state->getValue('existing_sub_id');

      if ($sub_id) {
        /**
         * Subscription entity being updated.
         *
         * @var \Drupal\asu_project_subscription\Entity\ProjectSubscription $subscription
         */
        // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
        $subscription = \Drupal::entityTypeManager()
          ->getStorage('asu_project_subscription')
          ->load($sub_id);

        if ($subscription) {
          // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
          $subscription->set('unsubscribed_at', \Drupal::time()->getRequestTime());
          $subscription->save();

          $this->messenger()->addStatus($this->t('You have been unsubscribed from notifications'));
          return;
        }
      }

      $this->messenger()->addError($this->t('Unsubscribe failed. Please try again.'));
      return;
    }

    $email_raw = $form_state->getValue('email');
    $email = mb_strtolower(trim((string) $email_raw));

    if (!$email) {
      $this->messenger()->addError($this->t('Please enter your email.'));
      return;
    }

    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $flood = \Drupal::service('flood');
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $clientIp = \Drupal::request()->getClientIp();
    $emailKey = hash('sha256', $email);

    $perProjectIdent = implode(':', ['asu_ps_proj', $project_id, $emailKey, $clientIp]);
    $globalIdent = implode(':', ['asu_ps_all', $emailKey, $clientIp]);

    $perProjectAllowed = $flood->isAllowed(
      'asu_project_subscription_submit_per_project',
      2,
      120,
      $perProjectIdent
    );
    $globalAllowed = $flood->isAllowed(
      'asu_project_subscription_submit_global',
      8,
      600,
      $globalIdent
    );

    if (!$perProjectAllowed || !$globalAllowed) {
      $this->messenger()->addError($this->t('Too many attempts. Please try again later.'));
      return;
    }
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $storage = \Drupal::entityTypeManager()
      ->getStorage('asu_project_subscription');
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $ids = \Drupal::entityQuery('asu_project_subscription')
      ->accessCheck(FALSE)
      ->condition('project', $project_id)
      ->condition('email', $email)
      ->range(0, 1)
      ->execute();

    // Common project meta used in both branches.
    $meta = $this->buildProjectMeta($project_id);

    if ($ids) {
      /**
       * Loaded subscription for re-confirmation flow.
       *
       * @var \Drupal\asu_project_subscription\Entity\ProjectSubscription $subscription
       */
      $subscription = $storage->load(reset($ids));

      $was_unsubscribed_at = (int) ($subscription->get('unsubscribed_at')->value ?? 0);

      if (!$was_unsubscribed_at) {
        $this->messenger()->addStatus($this->t('You are already subscribed to this project.'));
        return;
      }

      $confirm = Crypt::randomBytesBase64(32);
      $unsub = Crypt::randomBytesBase64(32);

      $subscription->set('is_confirmed', 0);
      $subscription->set('unsubscribed_at', NULL);
      $subscription->set('last_notified_state', NULL);
      $subscription->set('confirm_token', $confirm);
      $subscription->set('unsubscribe_token', $unsub);
      $subscription->save();

      $confirm_url = Url::fromRoute(
        'asu_project_subscription.confirm',
        ['token' => $confirm],
        ['absolute' => TRUE]
      )->toString();

      $unsub_url = Url::fromRoute(
        'asu_project_subscription.unsubscribe',
        ['token' => $unsub],
        ['absolute' => TRUE]
      )->toString();

      $sent = $this->sendConfirmationEmail($email, $confirm_url, $unsub_url, $meta);

      $this->finalizeEmailSend($sent, $flood, $perProjectIdent, $globalIdent, $email);
      return;
    }

    // New subscription branch.
    $confirm = Crypt::randomBytesBase64(32);
    $unsub = Crypt::randomBytesBase64(32);

    /**
     * New subscription entity to be created.
     *
     * @var \Drupal\asu_project_subscription\Entity\ProjectSubscription $subscription
     */
    $subscription = ProjectSubscription::create([
      'project' => $project_id,
      'email' => $email,
      'is_confirmed' => FALSE,
      'last_notified_state' => NULL,
      'confirm_token' => $confirm,
      'unsubscribe_token' => $unsub,
    ]);
    $subscription->save();

    $confirm_url = Url::fromRoute(
      'asu_project_subscription.confirm',
      ['token' => $confirm],
      ['absolute' => TRUE]
    )->toString();

    $unsub_url = Url::fromRoute(
      'asu_project_subscription.unsubscribe',
      ['token' => $unsub],
      ['absolute' => TRUE]
    )->toString();

    $sent = $this->sendConfirmationEmail($email, $confirm_url, $unsub_url, $meta);

    $this->finalizeEmailSend($sent, $flood, $perProjectIdent, $globalIdent, $email);
  }

  /**
   * Build project metadata for emails and UI.
   *
   * @param int $project_id
   *   Project node ID.
   *
   * @return array
   *   Array with 'title', 'address', 'url'.
   */
  private function buildProjectMeta($project_id) {
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $node = $this->entityTypeManager->getStorage('node')->load($project_id);
    $project_title = $node ? $node->label() : '';

    $address = '';
    if ($node) {
      $street = $node->get('field_street_address')->value ?? '';
      $postal = $node->get('field_postal_code')->value ?? '';
      $city = $node->get('field_city')->value ?? '';
      $parts = array_filter([$street, trim($postal . ' ' . $city)]);
      $address = implode(', ', $parts);
    }

    $project_url = Url::fromRoute(
      'entity.node.canonical',
      ['node' => $project_id],
      ['absolute' => TRUE]
    )->toString();

    return [
      'title' => $project_title,
      'address' => $address,
      'url' => $project_url,
    ];
  }

  /**
   * Send confirmation email for subscription flow.
   *
   * @param string $email
   *   Recipient email.
   * @param string $confirm_url
   *   Confirmation URL.
   * @param string $unsub_url
   *   Unsubscribe URL.
   * @param array $meta
   *   Project meta: title, address, url.
   *
   * @return bool
   *   TRUE on success, FALSE otherwise.
   */
  private function sendConfirmationEmail($email, $confirm_url, $unsub_url, array $meta) {
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $mailManager = \Drupal::service('plugin.manager.mail');
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $langcode = \Drupal::currentUser()->getPreferredLangcode();

    $result = $mailManager->mail(
      'asu_project_subscription',
      'confirm_subscription',
      $email,
      $langcode,
      [
        'confirm_url' => $confirm_url,
        'unsubscribe_url' => $unsub_url,
        'project_title' => $meta['title'] ?? '',
        'project_address' => $meta['address'] ?? '',
        'project_url' => $meta['url'] ?? '',
      ],
      NULL,
      TRUE
    );

    return !empty($result['result']);
  }

  /**
   * Finalize email sending: flood registration and user message.
   *
   * @param bool $sent
   *   Whether email sending succeeded.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   Flood service.
   * @param string $perProjectIdent
   *   Per-project identifier.
   * @param string $globalIdent
   *   Global identifier.
   * @param string $email
   *   Recipient email (for message).
   */
  private function finalizeEmailSend($sent, $flood, $perProjectIdent, $globalIdent, $email) {
    if ($sent) {
      $flood->register('asu_project_subscription_submit_per_project', 120, $perProjectIdent);
      $flood->register('asu_project_subscription_submit_global', 600, $globalIdent);

      $this->messenger()->addStatus($this->t('We sent a confirmation email to %mail.', ['%mail' => $email]));
    }
    else {
      $this->messenger()->addError($this->t('Failed to send confirmation email. Please try again later.'));
    }
  }

}
