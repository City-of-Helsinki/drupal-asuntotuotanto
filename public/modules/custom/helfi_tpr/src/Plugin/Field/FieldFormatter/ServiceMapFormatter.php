<?php

declare(strict_types = 1);

namespace Drupal\helfi_tpr\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\helfi_tpr\Entity\Unit;

/**
 * Field formatter to render service maps.
 *
 * @FieldFormatter(
 *   id = "service_map_embed",
 *   label = @Translation("TPR - Service map embed"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
final class ServiceMapFormatter extends FormatterBase {

  /**
   * The map base url.
   *
   * @var string
   */
  protected const BASE_URL = 'https://palvelukartta.hel.fi';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'iframe_title' => 'Service map',
      'link_title' => 'View larger map',
      'target' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['iframe_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Iframe title'),
      '#default_value' => $this->getSetting('iframe_title'),
    ];

    $elements['link_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link title'),
      '#default_value' => $this->getSetting('link_title'),
    ];

    $elements['target'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open link in new window'),
      '#return_value' => '_blank',
      '#default_value' => $this->getSetting('target'),
    ];

    return $elements;
  }

  /**
   * Generates the service map url for given entity.
   *
   * @param \Drupal\helfi_tpr\Entity\Unit $entity
   *   The tpr unit entity.
   * @param string|null $type
   *   The url type (embed or null).
   *
   * @return string
   *   The url.
   */
  protected function generateUrl(Unit $entity, ?string $type = NULL) : string {
    $type = $type ? sprintf('%s/', $type) : NULL;
    return sprintf('%s/%sunit/%s', static::BASE_URL, $type, $entity->id());
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    $entity = $items->getEntity();

    if (!$entity instanceof Unit) {
      throw new \InvalidArgumentException('The "service_map_embed" field can only be used with tpr_unit entities.');
    }
    foreach ($items as $delta => $item) {
      $element[$delta] = [
        'iframe' => [
          '#type' => 'html_tag',
          '#tag' => 'iframe',
          '#value' => '',
          '#attributes' => [
            'src' => $this->generateUrl($entity, 'embed'),
            'frameborder' => 0,
            'title' => $this->getSetting('iframe_title'),
          ],
        ],
        'link' => [
          '#type' => 'html_tag',
          '#tag' => 'a',
          '#value' => $this->getSetting('link_title'),
          '#attributes' => [
            'href' => $this->generateUrl($entity),
            'target' => $this->getSetting('target'),
          ],
        ],
      ];
    }

    return $element;
  }

}
