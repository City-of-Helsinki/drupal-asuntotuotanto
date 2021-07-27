<?php

namespace Drupal\asu_content\Plugin\ComputedField;

use Drupal\computed_field_plugin\Traits\ComputedSingleItemTrait;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Computed field new development status.
 *
 * @ComputedField(
 *   id = "asu_new_development_status",
 *   label = @Translation("Taxonomy as enum"),
 *   type = "asu_computed_render_array",
 *   entity_types = {"node"},
 *   bundles = {"apartment"}
 * )
 */
class NewDevelopmentStatus extends FieldItemList {
  use ComputedSingleItemTrait;

  /**
   * The reverse entity service.
   *
   * @var \Drupal\asu_content\CollectReverseEntity
   */
  protected $reverseEntities;

  /**
   * Constructs a NewDevelopmentStatus object.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $definition
   *   The data definition.
   * @param string $name
   *   (optional) The name of the created property, or NULL if it is the root
   *   of a typed data tree. Defaults to NULL.
   * @param \Drupal\Core\TypedData\TypedDataInterface $parent
   *   (optional) The parent object of the data property, or NULL if it is the
   *   root of a typed data tree. Defaults to NULL.
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    $this->reverseEntities = \Drupal::service('asu_content.collect_reverse_entity');
  }

  /**
   * Compute the enum value.
   *
   * @return mixed
   *   Returns the computed value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function singleComputeValue() {
    $current_entity = $this->getEntity();
    $reverse_references = $this->reverseEntities->getReverseReferences($current_entity);
    $value = FALSE;

    foreach ($reverse_references as $reference) {
      if (
        !empty($reference) &&
        $reference['referring_entity'] instanceof Node
      ) {
        $reverse_entity = $reference['referring_entity'];
        $id = $reverse_entity->field_new_development_status->target_id;
        if ($id && $term = Term::load($id)) {
          if (!$term->field_machine_readable_name ||
            !$value = $term->field_machine_readable_name->value) {
            $taxonomy_term_trans = \Drupal::service('entity.repository')->getTranslationFromContext($term, 'en');
            $name = trim($taxonomy_term_trans->getName());
            $value = strtoupper(
              str_replace(' ', '_', $name)
            );
            $value = str_replace('-', '_', $value);
          }
        }
      }
    }

    // @todo When displaying the field in twig add value&label through theme.
    // But do make note of search api index before adding theme function.
    return [
      '#markup' => $value,
    ];
  }

}
