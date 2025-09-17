<?php

namespace Drupal\asu_project_subscription\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Block\Annotation\Block;

/**
 * Provides a block with project subscription form.
 *
 * @Block(
 *   id = "asu_project_subscription_block",
 *   admin_label = @Translation("ASU Project Subscription Block")
 * )
 */
class ProjectSubscriptionBlock extends BlockBase {

  public function build() {
    $node = \Drupal::routeMatch()->getParameter('node');
    \Drupal::logger('asu_project_subscription')->notice('Block build hit. Node: ' . (is_object($node) ? $node->id() : 'none'));

    if ($node instanceof NodeInterface) {
      return \Drupal::formBuilder()->getForm(
        'Drupal\asu_project_subscription\Form\ProjectSubscriptionForm',
        $node
      );
    }
    return ['#markup' => '<div class="asu-subscription-debug">ASU subscription block (no node)</div>'];
  }

  public function getCacheContexts() {
    return ['route', 'url.path', 'languages:language_interface'];
  }

  public function getCacheMaxAge() {
    return 0;
  }
}
