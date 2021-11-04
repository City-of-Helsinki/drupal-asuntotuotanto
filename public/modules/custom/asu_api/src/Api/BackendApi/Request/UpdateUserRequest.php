<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\Request;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;

/**
 * Update user information in backend.
 */
class UpdateUserRequest extends Request {

  protected const METHOD = 'PUT';

  protected const PATH = '/v1/profiles/';

  /**
   * Form state.
   *
   * @var Drupal\Core\Form\FormStateInterface
   */
  private FormStateInterface $formState;

  /**
   * Fields to send.
   *
   * @var array
   */
  private array $fields;

  /**
   * User.
   *
   * @var Drupal\user\UserInterface
   */
  private UserInterface $user;

  /**
   * Constructor.
   */
  public function __construct(UserInterface $user, FormStateInterface $formState, array $fields) {
    $this->formState = $formState;
    $this->fields = $fields;
    $this->user = $user;
  }

  /**
   * {@inheritDoc}
   */
  public function getPath(): string {
    return static::PATH . $this->getBackendProfileId() . '/';
  }

  /**
   * Get user backend profile.
   */
  public function getBackendProfileId(): string {
    return $this->user->field_backend_profile->value;
  }

  /**
   * Update user request data to array.
   */
  public function toArray(): array {
    $data = [];
    foreach ($this->fields as $fieldName => $fieldInformation) {
      $data[$fieldInformation['external_field']] = $this->formState->getValue($fieldName);
    }

    $data['id'] = $this->user->uuid();
    $data['email'] = $this->user->getEmail();
    $data['contact_language'] = $this->user->getPreferredLangcode();

    return $data;
  }

}
