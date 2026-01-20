<?php

namespace Drupal\asu_content;

class IntegrationConstants {
  public const INTEGRATION_TRIGGERS = [
    'field_publish_on_oikotie' => self::OIKOTIE_REQUIRED_FIELDS,
    'field_publish_on_etuovi' => self::ETUOVI_REQUIRED_FIELDS,
  ];

  public const ETUOVI_REQUIRED_FIELDS = [
    'field_room_count'
  ];

  public const OIKOTIE_REQUIRED_FIELDS = [
    'field_application_url',
  ];
}
