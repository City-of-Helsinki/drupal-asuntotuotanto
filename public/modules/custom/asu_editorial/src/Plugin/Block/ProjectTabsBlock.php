<?php

namespace Drupal\asu_editorial\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'ProjectTabsBlock' block.
 *
 * @Block(
 *  id = "project_tabs_block",
 *  admin_label = @Translation("Project tabs block"),
 * )
 */
class ProjectTabsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['#label'] = $this->t('Projects');
    $build['#projects_own'] = views_embed_view('projects_user_page', 'projects_own');
    $build['#projects_all'] = views_embed_view('projects_user_page', 'projects_all');

    $build['#theme'] = 'project_tabs_block';
    $build['#attached']['library'][] = 'asu_editorial/project-tabs';

    return $build;
  }

}
