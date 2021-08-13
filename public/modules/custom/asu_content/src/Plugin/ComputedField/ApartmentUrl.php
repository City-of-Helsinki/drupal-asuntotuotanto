<?php

namespace Drupal\asu_content\Plugin\ComputedField;

use Drupal\computed_field_plugin\Traits\ComputedSingleItemTrait;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Site\Settings;

/**
 * Computer field for apartment and project urls.
 *
 * @ComputedField(
 *   id = "field_apartment_url",
 *   label = @Translation("Content url"),
 *   type = "asu_computed_render_array",
 *   entity_types = {"node"},
 *   bundles = {"apartment"}
 * )
 */
class ApartmentUrl extends FieldItemList {
  use ComputedSingleItemTrait;

  /**
   * Compute the apartment holding type value.
   *
   * @return mixed
   *   Returns the computed value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function singleComputeValue() {
    $current_entity = $this->getEntity();
    $value = FALSE;

    $baseurl = Settings::get('ASU_ASUNTOTUOTANTO_PUBLIC_URL');
    $type = 'apartment';
    $id = $current_entity->id();
    $value = "$baseurl/$type/$id";

    // @todo When displaying the field in twig add value&label through theme.
    // But do make note of search api index before adding theme function.
    return [
      '#markup' => $value,
    ];
  }

}
