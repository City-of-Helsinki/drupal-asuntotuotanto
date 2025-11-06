<?php

/**
 * Defines the Project Subscription entity.
 *
 * PHP version 8.1
 *
 * @category Drupal
 * @package  Asu_Project_Subscription
 * @author   Helsinki Dev Team <dev@hel.fi>
 * @license  https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link     https://www.drupal.org
 */

namespace Drupal\asu_project_subscription\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Project Subscription entity.
 *
 * @category Drupal
 * @package  Asu_Project_Subscription
 * @author   Helsinki Dev Team <dev@hel.fi>
 * @license  https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link     https://www.drupal.org
 *
 * @ContentEntityType(
 *   id = "asu_project_subscription",
 *   label = @Translation("Project subscription"),
 *   base_table = "asu_project_subscription",
 *   admin_permission = "administer asu project subscriptions",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode"
 *   }
 * )
 */
class ProjectSubscription extends ContentEntityBase
{
    /**
     * Defines base field definitions for the entity.
     *
     * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type Entity type
     *
     * @return array
     *   An array of base field definitions.
     */
    public static function baseFieldDefinitions(
        EntityTypeInterface $entity_type
    ): array {
        $fields = parent::baseFieldDefinitions($entity_type);

        $fields['id'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('ID'))
            ->setReadOnly(true);

        $fields['uuid'] = BaseFieldDefinition::create('uuid')
            ->setLabel(t('UUID'))
            ->setReadOnly(true);

        $fields['project'] = BaseFieldDefinition::create('entity_reference')
            ->setLabel(t('Project node'))
            ->setSetting('target_type', 'node')
            ->setRequired(true);

        $fields['email'] = BaseFieldDefinition::create('email')
            ->setLabel(t('Email'))
            ->setRequired(true);

        $fields['uid'] = BaseFieldDefinition::create('entity_reference')
            ->setLabel(t('User'))
            ->setSetting('target_type', 'user')
            ->setRequired(false);

        $fields['langcode']->setLabel(t('Language'));

        $fields['is_confirmed'] = BaseFieldDefinition::create('boolean')
            ->setLabel(t('Confirmed'))
            ->setDefaultValue(false);

        $fields['unsubscribed_at'] = BaseFieldDefinition::create('timestamp')
            ->setLabel(t('Unsubscribed at'))
            ->setDescription(t('If set, subscription is inactive.'))
            ->setRequired(false);

        $fields['last_notified_state'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Last notified state'))
            ->setSettings(['max_length' => 64])
            ->setRequired(false);

        $fields['created'] = BaseFieldDefinition::create('created')
        ->setLabel(t('Created'));

        $fields['changed'] = BaseFieldDefinition::create('changed')
        ->setLabel(t('Changed'));

        $fields['confirm_token'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Confirm token'))
            ->setSettings(['max_length' => 128])
            ->setRequired(true);

        $fields['unsubscribe_token'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Unsubscribe token'))
            ->setSettings(['max_length' => 128])
            ->setRequired(true);

        return $fields;
    }
}
