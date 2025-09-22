<?php

namespace Drupal\asu_content\Entity;

use Drupal\node\Entity\Node;
use Drupal\user\UserInterface;

/**
 * Class for node's project bundle.
 */
class Project extends Node {

  /**
   * Get apartment entities.
   *
   * @return mixed
   *   Array of apartment nodes.
   */
  public function getApartmentEntities() {
    return $this->field_apartments->referencedEntities();
  }

  /**
   * Check if application period is in future, ongoing or finished.
   *
   * @param string $period
   *   'Before' to check if application period is in future
   *   'Now' to check if application period is ongoing
   *   'After' if application period is in the past.
   *
   * @return bool
   *   Is application period.
   */
  public function isApplicationPeriod(string $period = 'now'): bool {
    if (!$this->field_application_start_time->value ||
        !$this->field_application_end_time->value) {
      return FALSE;
    }
    $startTime = asu_content_convert_datetime($this->field_application_start_time->value);
    $startTime = strtotime($startTime);
    $endTime = asu_content_convert_datetime($this->field_application_end_time->value);
    $endTime = strtotime($endTime);
    $now = time();

    $value = FALSE;
    switch ($period) {
      case "after":
        $value = $now > $endTime;
        break;

      case "now":
        $value = $now > $startTime && $now < $endTime;
        break;

      case "before":
        $value = $now < $startTime;
        break;
    }
    return $value;
  }

  /**
   * Get whether or not project accepts applications
   * after `application_end_time`.
   *
   * @return bool whether or not project accepts applications after application_end_time
   */
  public function getCanApplyAfterwards(): string {
    $field_can_apply_afterwards = $this->get('field_can_apply_afterwards');
    if ($field_can_apply_afterwards->isEmpty()) {
      return "";
    }
    \Drupal::logger('asu_application')->info("field_can_apply_afterwards: " . $field_can_apply_afterwards->value);
    return $field_can_apply_afterwards->value;
  }

  /**
   * Get ownership type entity reference as string.
   *
   * @return string
   *   Hitas or haso.
   */
  public function getOwnershipType(): string {
    if ($this->get('field_ownership_type')->isEmpty()) {
      return '';
    }
    $type = $this->field_ownership_type->referencedEntities()[0]->getName() ?? '';
    return strtolower($type);
  }

  /**
   * Get the application url for this project.
   *
   * @return string
   *   Application url.
   */
  public function getApplicationUrl($apartmentId = NULL): string {
    $baseurl = \Drupal::request()->getSchemeAndHttpHost();
    $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $baseurl = $baseurl . '/' . $langcode;
    $apartmentType = '';
    if (isset($this->field_ownership_type->referencedEntities()[0])) {
      $apartmentType = strtolower($this->field_ownership_type->referencedEntities()[0]->getName());
    }

    if ($this->isApplicationPeriod() || $this->isApplicationPeriod('before')) {
      if (!isset($this->field_ownership_type->referencedEntities()[0])) {
        return '';
      }
      return sprintf('%s/application/add/%s/%s', $baseurl, $apartmentType, $this->id());
    }

    if ($this->isApplicationPeriod('after')) {
      $queryParameter = $apartmentId ? "?apartment=$apartmentId" . '&project=' . $this->id() : '?project=' . $this->id();

      if ($this->getOwnershipType() == 'haso') {
        return sprintf('%s/application/add/%s/%s', $baseurl, $apartmentType, $this->id());
      }
      return sprintf('%s/contact/apply_for_free_apartment%s', $baseurl, $queryParameter);
    }
    return '';
  }

  /**
   * Get the amount of applications on any apartment on this project.
   *
   * @return int[]
   *   Apartment_id => amount of applications.
   */
  public function getApartmentApplicationCounts(): array {
    $database = \Drupal::database();
    $query = $database->select('asu_application', 'a');
    $query->leftJoin('asu_application__apartment', 'b', 'a.id = b.entity_id');
    $query->condition('a.project_id', $this->id());
    $query->condition('a.field_locked', 1);
    $query->fields('b', ['apartment_id']);
    $applications = $query->execute()->fetchAll();

    $count = [];
    foreach ($applications as $application) {
      $id = $application->apartment_id;
      $count[$id] = isset($count[$id]) ? $count[$id] + 1 : 1;
    }

    return $count;
  }

  /**
   * Can project be archived.
   *
   * Project can be archived after all apartments are sold.
   *
   * @return bool
   *   Can project be archived.
   */
  public function isArchievable(): bool {
    /** @var Apartment $apartment */
    foreach ($this->getApartmentEntities() as $apartment) {
      if (!$apartment->isSold()) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Get project sales person information.
   *
   * @return \Drupal\user\Entity\User
   *   Userinterface.
   */
  public function getSalesPerson(): ?UserInterface {
    $user_field = $this->get('field_salesperson');
    if ($user_field->isEmpty()) {
      return NULL;
    }

    return $user_field->referencedEntities()[0];
  }

}
