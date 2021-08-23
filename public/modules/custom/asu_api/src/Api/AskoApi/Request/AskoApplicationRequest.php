<?php

namespace Drupal\asu_api\Api\AskoApi\Request;

use Drupal\asu_application\Entity\Application;
use Drupal\user\Entity\User;

/**
 * Request sent to AS-KO.
 */
class AskoApplicationRequest {

  private const YES = 'kylla';

  private const NO = 'ei';

  private const SENIOR = 55;

  private User $user;

  private Application $application;

  private string $projectName;

  /**
   * Constructor.
   */
  public function __construct(User $user, Application $application, string $projectName) {
    $this->user = $user;
    $this->application = $application;
    $this->projectName = $projectName;
  }

  /**
   * Application request data to array.
   *
   * @return array
   *
   * @throws \Exception
   */
  public function toArray(): array {

    $store = \Drupal::service('asu_user.tempstore');
    $fields = \Drupal::config('asu_user.external_user_fields')->get('external_data_map');
    $variables = ['content' => []];
    foreach ($fields as $field_name => $field) {
      $variables['content'][$field_name] = $store->get($field_name);
    }

    $date = new \DateTime($this->user->date_of_birth->value);
    if ($this->application->field_personal_id->value && strlen($this->application->field_personal_id->value) === 5) {
      $personal_id = substr($this->application->field_personal_id->value, 1, 4);
    }
    else {
      $personal_id = $this->application->field_personal_id->value;
    }

    $data = [
      'etunimi' => $this->user->first_name->value,
      'sukunimi' => $this->user->last_name->value,
      'syntyma-aika' => $date->format('d.m.Y'),
      'hetuloppu' => $personal_id,
      'osoite' => $this->user->address->value,
      'postinumero' => $this->user->postal_code->value,
      'postitoimipaikka' => $this->user->city->value,
      'puhelin' => $this->user->phone_number->value,
      'email' => $this->user->getEmail(),
      'etunimi2' => '',
      'sukunimi2' => '',
      'syntyma-aika2' => '',
      'hetuloppu2' => '',
      'osoite2' => '',
      'postinumero2' => '',
      'postitoimipaikka2' => '',
      'puhelin2' => '',
      'email2' => '',
      'kohde' => $this->projectName,
      'huoneistonumero' => $this->getApartmentNumbers(),
      // Haso only.
      'jarjestysnumero' => '',
      '55_vuotias' => '',
      'ason_vaihtaja' => '',
      // Hitas only.
      'hitasomistus' => '',
      'lapsiperhe' => '',
      'lapsi1' => '',
    ];

    if ($this->application->hasAdditionalApplicant()) {
      if ($applicant = $this->getAdditionalApplicant()) {
        $data['etunimi2'] = $applicant['first_name'];
        $data['sukunimi2'] = $applicant['last_name'];
        $date2 = new \DateTime($applicant['date_of_birth']);
        $data['syntyma-aika2'] = $date2->format('d.m.Y');
        $data['hetuloppu2'] = $this->personalIdWithoutDivider($applicant['personal_id']);
        $data['osoite2'] = $applicant['address'];
        $data['postinumero2'] = $applicant['postal_code'];
        $data['postitoimipaikka2'] = $applicant['city'];
        $data['puhelin2'] = $applicant['phone'];
        $data['email2'] = $applicant['email'];
      }
    }

    if ($this->application->bundle() == 'hitas') {
      $data['hitasomistus'] = $this->userIsHitasOwner();
      $data['lapsiperhe'] = $this->userHasChildren();
      $data['lapsi1'] = '';
      unset($data['jarjestysnumero']);
      unset($data['55_vuotias']);
      unset($data['ason_vaihtaja']);
    }

    if ($this->application->bundle() == 'haso') {
      $data['jarjestysnumero'] = $this->application->field_right_of_residence_number->value;
      $data['55_vuotias'] = $this->userIsSenior();
      $data['ason_vaihtaja'] = $this->userIsAsoChanger();
      unset($data['hitasomistus']);
      unset($data['lapsiperhe']);
      unset($data['lapsi1']);
    }

    return $data;
  }

  /**
   * Return request data formatted for email.
   *
   * @return string
   */
  public function toMailFormat(): string {
    $body = '';
    foreach ($this->toArray() as $key => $value) {
      $body .= "$key: $value" . PHP_EOL;
    }
    return $body;
  }

  /**
   * Is user a senior.
   *
   * @return string
   *   Boolean as enum.
   *
   * @throws \Exception
   */
  private function userIsSenior(): string {
    $birthday = new \DateTime($this->user->date_of_birth->value);
    return $this::SENIOR <= $birthday->diff(new \DateTime('NOW'))->y ? $this::YES : $this::NO;
  }

  /**
   * Is user an aso changer.
   *
   * @return string
   *   Boolean as enum.
   */
  private function userIsAsoChanger(): string {
    return $this->application->field_aso_changer->value ? $this::YES : $this::NO;
  }

  /**
   * Is user hitas owner.
   *
   * @return string
   *   Boolean as enum.
   */
  private function userIsHitasOwner() : string {
    return $this->application->field_hitas_owner->value ? $this::YES : $this::NO;
  }

  /**
   * Does user have underage children.
   *
   * @return string
   *   Boolean as enum.
   */
  private function userHasChildren() : string {
    return $this->application->getHasChildren() ? $this::YES : $this::NO;
  }

  /**
   * Get selected apartments as comma separated string.
   *
   * @return string
   *   Selected apartment numbers as comma separated string.
   */
  private function getApartmentNumbers(): string {
    $apartments = $this->application->getApartments();
    $values = [];
    foreach ($apartments as $key => $apartment) {
      if ($apartment->id == 0) {
        continue;
      }
      $array = explode('|', $apartment->information);
      $values[] = trim(reset($array));
    }
    return implode(',', $values);
  }

  /**
   * Get additional applicant.
   *
   * @return array
   *   Additional applicants.
   */
  private function getAdditionalApplicant(): ?array {
    $applicants = $this->application->getApplicants();
    if (!empty($applicants)) {
      return $applicants[0];
    }
    return NULL;
  }

  /**
   * @param string $personalId
   * @return false|string
   */
  private function personalIdWithoutDivider(string $personalId) {
    return substr($personalId, 1, 4);
  }

}
