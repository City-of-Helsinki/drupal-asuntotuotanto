<?php

declare(strict_types=1);

namespace Drupal\asu_application\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Stores SAP-sent application payments in Drupal.
 *
 * @ContentEntityType(
 *   id = "asu_application_payment",
 *   label = @Translation("Application payment"),
 *   base_table = "asu_application_payment",
 *   admin_permission = "administer applications",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "created" = "created",
 *     "changed" = "changed"
 *   },
 *   unique_keys = {
 *     "payment_identity" = {
 *       "application_id",
 *       "reservation_id",
 *       "installment_type"
 *     }
 *   },
 *   handlers = {
 *     "access" = "Drupal\\asu_application\\Entity\\Access\\ApplicationPaymentAccess"
 *   }
 * )
 */
final class ApplicationPayment extends ContentEntityBase {

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

    $fields['reservation_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Reservation ID'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 128)
      ->setDefaultValue('');

    $fields['installment_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Installment type'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 128)
      ->setDefaultValue('');

    $fields['amount'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Amount'))
      ->setRequired(TRUE)
      ->setSetting('precision', 14)
      ->setSetting('scale', 2)
      ->setDefaultValue('0.00');

    $fields['due_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Due date'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'date');

    $fields['account_number'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Account number'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 64)
      ->setDefaultValue('');

    $fields['reference_number'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Reference number'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 128)
      ->setDefaultValue('');

    $fields['sap_sent'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Sent to SAP'))
      ->setRequired(TRUE)
      ->setDefaultValue(TRUE);

    $fields['source_correlation_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source correlation ID'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 128)
      ->setDefaultValue('');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'));

    return $fields;
  }

}
