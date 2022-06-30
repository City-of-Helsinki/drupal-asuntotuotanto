<?php

namespace Drupal\asu_elastic\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Convert indexed datetime to correct datetime timezone.
 *
 * @SearchApiDataType(
 *   id = "asu_date_time",
 *   label = @Translation("Date time"),
 *   description = @Translation("Covert real date time"),
 *   fallback_type = "string",
 * )
 */
class AsuDateTime extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    if (empty($value)) {
      return '';
    }

    if (is_array($value)) {
      $dates = [];

      foreach ($value as $date) {
        $dates[] = $this->convertDatetime($date);
      }

      $newvalue = $dates;
    }
    else {
      $newvalue = $this->convertDatetime($value);
    }

    return $newvalue;
  }

  /**
   * Covert datetime.
   */
  private function convertDatetime($value) {
    /** @var Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');
    $date = $date_formatter->format(
      strtotime($value . ' UTC'),
      'custom',
      'Y-m-d\TH:i:s',
      'Europe/Helsinki',
    );

    return $date;
  }

}
