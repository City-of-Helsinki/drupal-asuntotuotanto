<?php

namespace Drupal\asu_rest\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ProceduralCall;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\RemoveHook;

/**
 * OAuth-related hook adjustments for asu_rest.
 */
#[RemoveHook(
  hook: 'entity_base_field_info_alter',
  class: ProceduralCall::class,
  method: 'simple_oauth_entity_base_field_info_alter',
)]
class AsuRestOauthHooks {

  /**
   * Implements hook_entity_base_field_info_alter().
   */
  #[Hook('entity_base_field_info_alter')]
  public function entityBaseFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type): void {
    if ($entity_type->id() === 'consumer' && isset($fields['third_party'])) {
      $fields['third_party']->addConstraint('Oauth2PrivilegedConsumerField');
    }
  }

}
