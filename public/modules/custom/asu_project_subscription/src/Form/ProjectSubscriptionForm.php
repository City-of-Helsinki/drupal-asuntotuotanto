<?php

namespace Drupal\asu_project_subscription\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\asu_project_subscription\Entity\ProjectSubscription;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Defines the Project Subscription form.
 *
 * PHP version 8.1
 *
 * @category Drupal
 * @package  Asu_Project_Subscription
 * @author   Helsinki Dev Team <dev@hel.fi>
 * @license  https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later License
 * @link     https://www.drupal.org
 */

class ProjectSubscriptionForm extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'asu_project_subscription_form';
    }

    /**
     * Builds the project subscription form.
     *
     * @param array                                $form       Form structure
     * @param \Drupal\Core\Form\FormStateInterface $form_state Form state
     * @param \Drupal\node\NodeInterface|null      $project    Project
     *
     * @return array
     *   The render array of the form.
     */
    public function buildForm(
        array $form,
        FormStateInterface $form_state,
        $project = null
    ) {
        $form['project'] = [
        '#type' => 'hidden',
        '#value' => $project->id(),
        ];

        $account = \Drupal::currentUser();
        $user_email = null;
        if ($account->isAuthenticated()) {
            if ($user = User::load((int) $account->id())) {
                $user_email = $user->getEmail() ?: null;
            }
        }

        $form['hp_website'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Website'),
        '#attributes' => ['autocomplete' => 'off'],
        '#access' => false,
        ];

        $has_active_subscription = false;
        $existing_sub_id = null;

        if ($user_email) {
            $ids = \Drupal::entityQuery('asu_project_subscription')
                ->accessCheck(false)
                ->condition('project', $project->id())
                ->condition('email', $user_email)
                ->condition('is_confirmed', 1)
                ->condition('unsubscribed_at', null, 'IS NULL')
                ->range(0, 1)
                ->execute();

            if ($ids) {
                $existing_sub_id = reset($ids);
                $has_active_subscription = true;
            }
        }

        $form['mode'] = [
        '#type' => 'hidden',
        '#value' => $has_active_subscription ? 'unsubscribe' : 'subscribe',
        ];

        if ($has_active_subscription) {
            $form['status'] = [
            '#type' => 'item',
            '#markup' => $this->t(
                'You are subscribed to updates for this property.'
            ),
            '#attributes' => ['class' => ['asu-ps-status']],
            ];
            $form['existing_sub_id'] = [
            '#type' => 'hidden',
            '#value' => $existing_sub_id,
            ];
        } else {
            $placeholder = $user_email ?: (string) $this->t('Your email address');

            $form['email'] = [
            '#type' => 'email',
            '#title' => $this->t('Your email address'),
            '#title_display' => 'invisible',
            '#required' => true,
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

        $form['actions'] = ['#type' => 'actions'];

        $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $has_active_subscription
        ? $this->t('Unsubscribe')
        : $this->t('Subscribe'),
        '#button_type' => 'primary',
        ];

        return $form;
    }

    /**
     * Form submit handler for the project subscription form.
     *
     * @param array                                $form       Form structure
     * @param \Drupal\Core\Form\FormStateInterface $form_state Form state
     *
     * @return void
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
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
                 * @var \Drupal\asu_project_subscription\Entity\ProjectSubscription
                 *   $subscription
                 */
                $subscription = \Drupal::entityTypeManager()
                    ->getStorage('asu_project_subscription')
                    ->load($sub_id);

                if ($subscription) {
                    $subscription->set(
                        'unsubscribed_at',
                        \Drupal::time()->getRequestTime()
                    );
                      $subscription->save();

                    $this->messenger()->addStatus(
                        $this->t('You have been unsubscribed.')
                    );
                      return;
                }
            }

            $this->messenger()->addError(
                $this->t('Unsubscribe failed. Please try again.')
            );
            return;
        }

        $email_raw = $form_state->getValue('email');
        $email = mb_strtolower(trim((string) $email_raw));

        if (!$email) {
            $this->messenger()->addError($this->t('Please enter your email.'));
            return;
        }

        $flood = \Drupal::service('flood');
        $clientIp = \Drupal::request()->getClientIp();
        $emailKey = hash('sha256', $email);

        $perProjectIdent = implode(
            ':',
            ['asu_ps_proj', $project_id, $emailKey, $clientIp]
        );
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
            $this->messenger()->addError(
                $this->t('Too many attempts. Please try again later.')
            );
            return;
        }

        $storage = \Drupal::entityTypeManager()
        ->getStorage('asu_project_subscription');

        $ids = \Drupal::entityQuery('asu_project_subscription')
            ->accessCheck(false)
            ->condition('project', $project_id)
            ->condition('email', $email)
            ->range(0, 1)
            ->execute();

        $node = \Drupal\node\Entity\Node::load($project_id);
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
            ['absolute' => true]
        )->toString();

        if ($ids) {
            /**
             * Loaded subscription for re-confirmation flow.
             *
             * @var \Drupal\asu_project_subscription\Entity\ProjectSubscription
             *   $subscription
             */
            $subscription = $storage->load(reset($ids));

            $was_unsubscribed_at = (int) (
            $subscription->get('unsubscribed_at')->value ?? 0
            );

            if (!$was_unsubscribed_at) {
                $this->messenger()->addStatus(
                    $this->t('You are already subscribed to this project.')
                );
                  return;
            }

            $confirm = Crypt::randomBytesBase64(32);
            $unsub = Crypt::randomBytesBase64(32);

            $subscription->set('is_confirmed', 0);
            $subscription->set('unsubscribed_at', null);
            $subscription->set('last_notified_state', null);
            $subscription->set('confirm_token', $confirm);
            $subscription->set('unsubscribe_token', $unsub);
            $subscription->save();

            $confirm_url = Url::fromRoute(
                'asu_project_subscription.confirm',
                ['token' => $confirm],
                ['absolute' => true]
            )->toString();

            $unsub_url = Url::fromRoute(
                'asu_project_subscription.unsubscribe',
                ['token' => $unsub],
                ['absolute' => true]
            )->toString();

            $mailManager = \Drupal::service('plugin.manager.mail');
            $langcode = \Drupal::currentUser()->getPreferredLangcode();

            $result = $mailManager->mail(
                'asu_project_subscription',
                'confirm_subscription',
                $email,
                $langcode,
                [
                'confirm_url' => $confirm_url,
                'unsubscribe_url' => $unsub_url,
                'project_title' => $project_title,
                'project_address' => $address,
                'project_url' => $project_url,
                ],
                null,
                true
            );

            if (!empty($result['result'])) {
                $flood->register(
                    'asu_project_subscription_submit_per_project',
                    120,
                    $perProjectIdent
                );
                $flood->register(
                    'asu_project_subscription_submit_global',
                    600,
                    $globalIdent
                );

                $this->messenger()->addStatus(
                    $this->t(
                        'We sent a confirmation email to %mail.',
                        ['%mail' => $email]
                    )
                );
            } else {
                $this->messenger()->addError(
                    $this->t(
                        'Failed to send confirmation email. Please try again later.'
                    )
                );
            }
            return;
        }

        $confirm = Crypt::randomBytesBase64(32);
        $unsub = Crypt::randomBytesBase64(32);

        /**
         * New subscription entity to be created.
         *
         * @var \Drupal\asu_project_subscription\Entity\ProjectSubscription
         *   $subscription
         */
        $subscription = ProjectSubscription::create(
            [
            'project' => $project_id,
            'email' => $email,
            'is_confirmed' => false,
            'last_notified_state' => null,
            'confirm_token' => $confirm,
            'unsubscribe_token' => $unsub,
            ]
        );
        $subscription->save();

        $confirm_url = Url::fromRoute(
            'asu_project_subscription.confirm',
            ['token' => $confirm],
            ['absolute' => true]
        )->toString();

        $unsub_url = Url::fromRoute(
            'asu_project_subscription.unsubscribe',
            ['token' => $unsub],
            ['absolute' => true]
        )->toString();

        $mailManager = \Drupal::service('plugin.manager.mail');
        $langcode = \Drupal::currentUser()->getPreferredLangcode();

        $result = $mailManager->mail(
            'asu_project_subscription',
            'confirm_subscription',
            $email,
            $langcode,
            [
            'confirm_url' => $confirm_url,
            'unsubscribe_url' => $unsub_url,
            'project_title' => $project_title,
            'project_address' => $address,
            'project_url' => $project_url,
            ],
            null,
            true
        );

        if (!empty($result['result'])) {
            $flood->register(
                'asu_project_subscription_submit_per_project',
                120,
                $perProjectIdent
            );
            $flood->register(
                'asu_project_subscription_submit_global',
                600,
                $globalIdent
            );

            $this->messenger()->addStatus(
                $this->t(
                    'We sent a confirmation email to %mail.',
                    ['%mail' => $email]
                )
            );
        } else {
            $this->messenger()->addError(
                $this->t(
                    'Failed to send confirmation email. Please try again later.'
                )
            );
        }
    }

}
