<?php

declare(strict_types = 1);

namespace Drupal\helfi_tpr\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogEntityTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Entity\RemoteEntityBase;

/**
 * Defines the base class for all TPR entities.
 */
abstract class TprEntityBase extends RemoteEntityBase implements RevisionableInterface {

  use RevisionLogEntityTrait;

  /**
   * Creates a basic string field.
   *
   * @param string $label
   *   The label.
   * @param int $cardinality
   *   The cardinality.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition
   *   The field definition.
   */
  protected static function createStringField(string $label, int $cardinality = 1) : BaseFieldDefinition {
    return BaseFieldDefinition::create('string')
      // @codingStandardsIgnoreLine
      ->setLabel(new TranslatableMarkup($label))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality($cardinality)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ]);
  }

  /**
   * Creates a basic link field.
   *
   * @param string $label
   *   The label.
   * @param int $cardinality
   *   The cardinality.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition
   *   The field definition.
   */
  protected static function createLinkField(string $label, int $cardinality = 1) : BaseFieldDefinition {
    return BaseFieldDefinition::create('link')
      // @codingStandardsIgnoreLine
      ->setLabel(new TranslatableMarkup($label))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setCardinality($cardinality)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::revisionLogBaseFieldDefinitions($entity_type);
    $fields['name'] = static::createStringField('Name');

    return $fields;
  }

}
