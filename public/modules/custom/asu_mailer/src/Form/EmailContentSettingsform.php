<?php

namespace Drupal\asu_mailer\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to save configurations for emails.
 */
class EmailContentSettingsform extends ConfigFormBase {

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return parent::create($container);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'asu_mailer_email_content_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'asu_mailer.email_content_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('asu_mailer.email_content_settings');
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    foreach ($this->getFields() as $formId => $details) {
      $configKey = $formId . '_' . $language;
      $config->set($configKey, $form_state->getValue($formId));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('asu_mailer.email_content_settings');
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    foreach ($this->getFields() as $formId => $details) {
      $configKey = $formId . '_' . $language;
      $form[$formId] = [
        '#type' => $details['type'],
        '#title' => $this->t($details['title']),
        '#default_value' => $config->get($configKey) ?? '',
        '#required' => TRUE,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Get fields.
   *
   * @return array
   *   Array of configuration fields.
   */
  private function getFields(): array {
    return [
      'hitas_application_created_subject' => [
        'type' => 'textfield',
        'title' => 'Hitas application created email subject',
      ],
      'hitas_application_created_text' => [
        'type' => 'textarea',
        'title' => 'Hitas application created email text',
      ],
      'haso_application_created_subject' => [
        'type' => 'textfield',
        'title' => 'Haso application created email subject',
      ],
      'haso_application_created_text' => [
        'type' => 'textarea',
        'title' => 'haso application created email text',
      ],
    ];
  }

}
