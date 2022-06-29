<?php

namespace Drupal\asu_elastic\Plugin\search_api\data_type;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Entity\DateFormat;
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

    /** @var DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');
    $date = $date_formatter->format(
      strtotime($value.' UTC'),
      'custom',
      'Y-m-d\TH:i:s',
      'Europe/Helsinki',
    );

    return $date;
  }

}
