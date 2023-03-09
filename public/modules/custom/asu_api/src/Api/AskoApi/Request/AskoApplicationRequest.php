<?php

namespace Drupal\asu_api\Api\AskoApi\Request;

use Drupal\asu_application\Entity\Application;

/**
 * Request sent to AS-KO.
 */
class AskoApplicationRequest {

  private const YES = 'kyllä';

  private const NO = 'ei';

  private const SENIOR = 55;

  /**
   * Application object.
   *
   * @var Drupal\asu_application\Entity\Application
   */
  private Application $application;

  /**
   * Name of the project.
   *
   * @var string
   */
  private string $projectName;

  /**
   * Constructor.
   */
  public function __construct(Application $application, string $projectName) {
    $this->application = $application;
    $this->projectName = $projectName;
  }

  /**
   * Application request data to array.
   *
   * @return array
   *   Asko application request data as an array.
   *
   * @throws \Exception
   */
  public function toArray(): array {
    $main_applicant = $this->getMainApplicant();
    $syntyma_aika = new \DateTime($main_applicant->syntyma_aika);

    $data = [
      'etunimi' => $main_applicant->etunimi,
      'sukunimi' => $main_applicant->sukunimi,
      'syntyma-aika' => $syntyma_aika->format('d.m.Y'),
      'hetuloppu' => $this->personalIdWithoutDivider($main_applicant->hetuloppu),
      'osoite' => $main_applicant->osoite,
      'postinumero' => $main_applicant->postinumero,
      'postitoimipaikka' => $main_applicant->postitoimipaikka,
      'puhelin' => $main_applicant->puhelin,
      'email' => $main_applicant->email,
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
        $data['email2'] = $applicant['email']->value;
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
      $data['55_vuotias'] = $this->userIsSenior($main_applicant->syntyma_aika);
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
   *   Asko application formatted properly for email.
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
  private function userIsSenior($date_of_birth): string {
    $birthday = new \DateTime($date_of_birth);
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
    $applicants = $this->application->getApplicant();
    if (!empty($applicants)) {
      return $applicants[0];
    }
    return NULL;
  }

  /**
   * Get the pid without decade divider.
   *
   * @param string $personalId
   *   Pid.
   *
   * @return false|string
   *   Personal id without divider.
   */
  private function personalIdWithoutDivider(string $personalId) {
    if (strlen($personalId) == 4) {
      return $personalId;
    }
    return substr($personalId, 1, 4);
  }

  /**
   * Get additional applicant.
   *
   * @return object
   *   Applicant information.
   */
  protected function getMainApplicant(): ?object {
    if (!$this->application->getMainApplicant()) {
      return NULL;
    }

    $applicant = $this->application->getMainApplicant()[0];
    return (object) [
      'etunimi' => $applicant['first_name'],
      'sukunimi' => $applicant['last_name'],
      'email' => $applicant['email'],
      'puhelin' => $applicant['phone'],
      'osoite' => $applicant['address'],
      'postitoimipaikka' => $applicant['city'],
      'postinumero' => $applicant['postal_code'],
      'syntyma_aika' => $applicant['date_of_birth'],
      'hetuloppu' => $applicant['personal_id'],
    ];
  }

}
