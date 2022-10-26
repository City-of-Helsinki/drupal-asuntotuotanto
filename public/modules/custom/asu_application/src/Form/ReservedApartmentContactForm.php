<?php

namespace Drupal\asu_application\Form;

use Drupal\node\Entity\Node;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Reserved apartment contact form.
 */
class ReservedApartmentContactForm extends FormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'asu_reserved_apartment_contact_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $user_id = NULL, string $project_id = NULL) {
    $project_id = \Drupal::request()->get('project') ?? NULL;
    $project = NULL;
    $contact_person_value = NULL;

    if ($project_id) {
      $project = Node::load($project_id);

      if ($salesperson = $project->getSalesPerson()) {
        $contact_person_value = $salesperson->getEmail();
      }
    }

    $form['#contact_form_title'] = t('Apply for an apartment');
    $form['#contact_form_text'] = t('Leave your contact information and we will personally contact you regarding this apartment.');

    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#required' => TRUE,
    ];

    $form['field_apartment_information'] = [
      '#type' => 'textfield',
      '#title' => t('Apartment information'),
    ];

    $form['field_email'] = [
      '#type' => 'email',
      '#title' => t('Email'),
      '#required' => TRUE,
    ];

    $form['field_phone'] = [
      '#type' => 'textfield',
      '#title' => t('Phone number'),
      '#required' => TRUE,
    ];

    $form['field_date_of_birth'] = [
      '#type' => 'date',
      '#title' => t('Date of birth'),
      '#required' => TRUE,
    ];

    $form['field_message'] = [
      '#type' => 'textarea',
      '#title' => t('Message'),
    ];

    $form['field_contact_person'] = [
      '#type' => 'hidden',
      '#value' => $contact_person_value,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Apply'),
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $project_id = \Drupal::request()->get('project') ?? NULL;
    $values = $form_state->cleanValues()->getValues();
    $body = $this->convertMessage($values);

    /** @var \Drupal\Core\Mail\MailManager $mailManager */
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'asu_application';
    $key = 'apply_for_free_apartment';
    $to = $values['field_contact_person'];
    $langcode = 'fi';
    $send = TRUE;
    $subject = 'Yhteydenottopyyntö vapaaseen huoneistoon' . $values->field_apartment_information;
    $params = [
      'subject' => $subject,
      'message' => $body,
    ];

    $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
    $this->messenger()->addStatus($this->t('Thank you for the application, we will be in touch'));
    $form_state->setRedirect('entity.node.canonical', ['node' => $project_id]);
  }

  /**
   * Convert form values to email message.
   *
   * @param array $values
   *   Form values.
   *
   * @return string
   *   Email body.
   */
  private function convertMessage(array $values): string {
    $message_values = [
      'Name' => $values['field_name'],
      'Apartment information' => $values['field_apartment_information'],
      'Phone' => $values['field_phone'],
      'Date of birth' => $values['field_date_of_birth'],
      'Message' => $values['field_message'],
    ];

    $body = "Käyttäjä täytti hakemuslomakkeen vapaaseen huoneistoon: \r\n";

    foreach ($message_values as $key => $value) {
      $body .= "$key: $value" . PHP_EOL;
    }

    return $body;
  }

}
