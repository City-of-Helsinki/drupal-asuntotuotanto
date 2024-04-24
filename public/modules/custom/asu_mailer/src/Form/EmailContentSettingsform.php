<?php

namespace Drupal\asu_mailer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to save configurations for emails.
 */
class EmailContentSettingsform extends ConfigFormBase {

  /**
   * Constructor.
   */
  public function __construct(LanguageManager $languageManager) {
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager')
    );
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
    $language = $this->languageManager->getCurrentLanguage()->getId();

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
    $language = $this->languageManager->getCurrentLanguage()->getId();

    foreach ($this->getFields() as $formId => $details) {
      $configKey = $formId . '_' . $language;
      $form[$formId] = [
        '#type' => $details['type'],
        '#title' => $this->t(
          '@email_form_title',
          ['@email_form_title', $details['title']]
        ),
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
