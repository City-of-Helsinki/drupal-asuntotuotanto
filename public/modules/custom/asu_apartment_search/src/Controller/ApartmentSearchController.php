<?php

namespace Drupal\asu_apartment_search\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * An asu_apartment_search controller.
 */
class ApartmentSearchController extends ControllerBase {

  /**
   * Returns a renderable array for a asuntohaku page.
   */
  public function content() {
    // @todo Should be replaced with block when frontend theme is available.
    $build = [
      '#markup' => '<div id="search"></div>',
      '#attached' => [
        'library' => 'asu_apartment_search/apartment-search',
      ],
    ];
    return $build;
  }

}
