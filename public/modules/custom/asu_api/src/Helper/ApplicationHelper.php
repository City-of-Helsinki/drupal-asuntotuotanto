<?php

namespace Drupal\asu_api\Helper;

/**
 * Application helper.
 */
class ApplicationHelper {

  /**
   * Get difference between two dates in years.
   *
   * @param string $date
   *   Date to compare to 'now'.
   *
   * @return int
   *   Difference between now and the given date in years.
   */
  public static function dateDifferenceYears(string $date) {
    $date = new \DateTime($date);
    $now = new \DateTime();
    $interval = $now->diff($date);
    return $interval->y;
  }

  /**
   * Calculates person's age from PID.
   *
   * @param string $pid
   *   Personal identification number.
   *
   * @return int
   *   Age in years.
   */
  public static function calculateAgeFromPid(string $pid): int {
    $century = substr($pid, 7, 1) === "-" ? 19 : 20;
    $year = substr($pid, 5, 2);

    $day = substr($pid, 1, 2);
    $month = substr($pid, 3, 2);

    $date = "{$day}-{$month}-{$century}{$year}";

    return self::dateDifferenceYears($date);
  }

  /**
   * Format date to proper format for api.
   *
   * @param string $date
   *   Date to format for the backend api.
   *
   * @return string
   *   Formatted date string.
   *
   * @throws \Exception
   */
  public static function formatDate(string $date): string {
    return (new \DateTime($date))->format('Y-m-d');
  }

}
