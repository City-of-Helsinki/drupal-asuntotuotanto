<?php

namespace Drupal\asu_project_subscription\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\asu_project_subscription\Entity\ProjectSubscription;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Url;
use Drupal\Core\Mail\MailManagerInterface;

class ProjectSubscriptionForm extends FormBase {

  public function getFormId() {
    return 'asu_project_subscription_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $project = NULL) {
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your email'),
      '#required' => TRUE,
    ];

    $form['project'] = [
      '#type' => 'hidden',
      '#value' => $project->id(),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Subscribe'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('email');
    $project_id = $form_state->getValue('project');

    $confirm = Crypt::randomBytesBase64(32);
    $unsub   = Crypt::randomBytesBase64(32);

    $subscription = ProjectSubscription::create([
      'project' => $project_id,
      'email' => $email,
      'is_confirmed' => FALSE,
      'last_notified_state' => '',
      'confirm_token' => $confirm,
      'unsubscribe_token' => $unsub,
    ]);
    $subscription->save();

    $confirm_url = Url::fromRoute('asu_project_subscription.confirm', ['token' => $confirm], ['absolute' => TRUE])->toString();
    $unsub_url   = Url::fromRoute('asu_project_subscription.unsubscribe', ['token' => $unsub], ['absolute' => TRUE])->toString();

    $mailManager = \Drupal::service('plugin.manager.mail');
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $result = $mailManager->mail(
      'asu_project_subscription',             // module
      'confirm_subscription',                 // key
      $email,                                 // to
      $langcode,                              // langcode
      ['confirm_url' => $confirm_url, 'unsubscribe_url' => $unsub_url], // params
      NULL,                                   // from (by default site email)
      TRUE                                    // send
    );

    if (!empty($result['result'])) {
      $this->messenger()->addStatus($this->t('We sent a confirmation email to %mail.', ['%mail' => $email]));
    } else {
      $this->messenger()->addError($this->t('Failed to send confirmation email. Please try again later.'));
    }
  }
}
