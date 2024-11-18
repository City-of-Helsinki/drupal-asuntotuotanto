<?php

namespace Drupal\asu_application\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Reserved apartment contact form.
 */
class ReservedApartmentContactForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A request stack symfony instance.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->requestStack = $container->get('request_stack');
    $instance->mailManager = $container->get('plugin.manager.mail');

    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'asu_reserved_apartment_contact_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $user_id = NULL, ?string $project_id = NULL) {
    $project_id = $this->requestStack->getCurrentRequest()->get('project') ?? NULL;
    $apartment_id = $this->requestStack->getCurrentRequest()->get('apartment') ?? NULL;
    $project = NULL;
    $contact_person_value = NULL;

    if ($project_id) {
      $project = $this->entityTypeManager->getStorage('node')->load($project_id);

      if ($salesperson = $project->getSalesPerson()) {
        $contact_person_value = $salesperson->getEmail();
      }
    }

    // If no project is found present error message to the client
    // and redirect to page they came from.
    if (!$project) {
      $this->messenger()->addError($this->t('No project found. Try again or contact @email', ['@email' => getenv('DRUPAL_DEFAULT_FORM_EMAIL')]));
      $referer = $this->requestStack->getCurrentRequest()->headers->get('referer');
      if ($referer) {
        return new RedirectResponse($referer);
      }
      return $this->redirect('<front>');
    }

    $form['#contact_form_title'] = $this->t('Apply for an apartment');
    $form['#contact_form_text'] = $this->t('Leave your contact information and we will personally contact you regarding this apartment.');

    $form['field_project'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project'),
      '#value' => $project->getTitle(),
      '#required' => TRUE,
      '#disabled' => TRUE,
    ];

    $form['field_apartment_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Apartment'),
      '#required' => TRUE,
      '#value' => $apartment_id,
      '#disabled' => TRUE,
    ];

    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
    ];

    $form['field_apartment_information'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Apartment information'),
    ];

    $form['field_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];

    $form['field_phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone number'),
      '#required' => TRUE,
    ];

    $form['field_date_of_birth'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of birth'),
      '#required' => TRUE,
    ];

    $form['field_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
    ];

    $form['field_contact_person'] = [
      '#type' => 'hidden',
      '#value' => $contact_person_value,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['#cache'] = ['max-age' => 0];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $project_id = $this->requestStack->getCurrentRequest()->get('project') ?? NULL;
    $values = $form_state->cleanValues()->getValues();
    $body = $this->convertMessage($values);
    $email_to = $values['field_contact_person'];
    $user = NULL;

    if ($email_to) {
      $user = $this->entityTypeManager->getStorage('user')
        ->loadByProperties([
          'mail' => $values['field_contact_person'],
          'type' => 'sales',
        ]);
      $user = reset($user);
    }

    // If salesperson not exist use default email address.
    if (!$user) {
      $email_to = getenv('DRUPAL_DEFAULT_FORM_EMAIL');
    }

    if (!$email_to) {
      $this->messenger()->addError($this->t('Could not send the email, please contact us at asuntomyynti@hel.fi'));
    }
    else {
      $module = 'asu_application';
      $key = 'apply_for_free_apartment';
      $to = $email_to;
      $langcode = 'fi';
      $send = TRUE;
      $subject = 'Yhteydenottopyyntö vapaaseen huoneistoon' . $values['field_apartment_information'];
      $params = [
        'subject' => $subject,
        'message' => $body,
      ];

      $this->mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
      $this->messenger()->addStatus($this->t('Thank you for the application, we will be in touch'));
    }

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
    $date = new \DateTime($values['field_date_of_birth']);

    $message_values = [
      'Projekti' => $values['field_project'],
      'Asunto' => $values['field_apartment_id'],
      'Nimi' => $values['field_name'],
      'Asunnon tiedot' => $values['field_apartment_information'],
      'Sähköposti' => $values['field_email'],
      'Puhelinnumero' => $values['field_phone'],
      'Syntymäaika' => $date->format('d.m.Y'),
      'Viesti' => $values['field_message'],
    ];

    $body = "Käyttäjä täytti hakemuslomakkeen vapaaseen huoneistoon: \r\n";

    foreach ($message_values as $key => $value) {
      $body .= "$key: $value" . PHP_EOL;
    }

    return $body;
  }

}
