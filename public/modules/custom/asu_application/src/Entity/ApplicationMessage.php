<?php

declare(strict_types=1);

namespace Drupal\asu_application\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the application message entity.
 *
 * @ContentEntityType(
 *   id = "asu_application_message",
 *   label = @Translation("Application message"),
 *   base_table = "asu_application_message",
 *   admin_permission = "administer applications",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "created" = "created",
 *     "changed" = "changed"
 *   },
 *   handlers = {
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler"
 *   }
 * )
 */
final class ApplicationMessage extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setReadOnly(TRUE);

    $fields['application_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Application ID'))
      ->setRequired(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['project_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Project ID'))
      ->setRequired(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['sender_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sender'))
      ->setRequired(FALSE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['salesperson_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Salesperson'))
      ->setRequired(FALSE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['sender_role'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Sender role'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 32)
      ->setDefaultValue('customer');

    $fields['recipient_mail'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Recipient email'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 255)
      ->setDefaultValue('');

    $fields['body'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Message'))
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'));

    return $fields;
  }

}
