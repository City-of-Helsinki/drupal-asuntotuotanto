<?php

namespace Drupal\asu_project_subscription\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\node\NodeInterface;

/**
 * Defines the Project Subscription block.
 *
 * @category Drupal
 * @package Asu_Project_Subscription
 * @author Helsinki Dev Team <dev@hel.fi>
 * @license https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later License
 * @link https://www.drupal.org
 *
 * @Block(
 *   id = "asu_project_subscription_block",
 *   admin_label = @Translation("ASU Project subscription block")
 * )
 */
class ProjectSubscriptionBlock extends BlockBase {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   A render array for the subscription form or a fallback markup.
   */
  public function build() {
    $node = \Drupal::routeMatch()->getParameter('node');

    $node_id = is_object($node) ? $node->id() : 'none';
    \Drupal::logger('asu_project_subscription')->notice('Block build hit. Node: @id', ['@id' => $node_id]);

    if ($node instanceof NodeInterface) {
      return \Drupal::formBuilder()->getForm('Drupal\asu_project_subscription\Form\ProjectSubscriptionForm', $node);
    }

    return [
      '#markup' => '<div class="asu-subscription-debug">ASU subscription block (no node)</div>',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @return string[]
   *   Cache contexts affecting the block.
   */
  public function getCacheContexts() {
    return ['route', 'url.path', 'languages:language_interface'];
  }

  /**
   * {@inheritdoc}
   *
   * @return int
   *   The max-age for this block (zero = no caching).
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
