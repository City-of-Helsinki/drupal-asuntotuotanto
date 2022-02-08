<?php

namespace Drupal\asu_content\Entity;

use Drupal\node\Entity\Node;

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
  public function isApplicationPeriod(string $period = 'now') {
    if (!$this->field_application_start_time->value ||
        !$this->field_application_end_time->value) {
      return FALSE;
    }
    $startTime = strtotime($this->field_application_start_time->value);
    $endTime = strtotime($this->field_application_end_time->value);
    $now = time();

    if (!$startTime || !$endTime) {
      return FALSE;
    }

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
   * Get the application url for this project.
   *
   * @return string
   *   Application url.
   */
  public function getApplicationUrl($apartmentId = NULL): string {
    $baseurl = \Drupal::request()->getSchemeAndHttpHost();
    if ($this->isApplicationPeriod() || $this->isApplicationPeriod('before')) {
      $apartmentType = strtolower($this->field_ownership_type->referencedEntities()[0]->getName());
      return sprintf('%s/application/add/%s/%s', $baseurl, $apartmentType, $this->id());
    }
    if ($this->isApplicationPeriod('after')) {
      $queryParameter = $apartmentId ? "?apartment=$apartmentId" : '?project=' . $this->id();
      return sprintf('%s/contact/apply_for_free_apartment%s', $baseurl, $queryParameter);
    }
    return '';
  }

}