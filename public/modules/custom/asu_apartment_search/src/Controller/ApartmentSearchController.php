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
  public function hitas(): array {
    // @todo Should be replaced with block when frontend theme is available.
    $build = [
      '#markup' => '<div id="asu_react_search"></div>',
      '#attached' => [
        'library' => 'asu_apartment_search/hitas-apartment-search',
      ],
    ];
    return $build;
  }

  /**
   * Returns a renderable array for a asuntohaku page.
   */
  public function hitasUpcoming(): array {
    // @todo Should be replaced with block when frontend theme is available.
    $build = [
      '#markup' => '<div id="asu_react_search"></div>',
      '#attached' => [
        'library' => 'asu_apartment_search/hitas-apartment-upcoming',
      ],
    ];
    return $build;
  }

  /**
   * Returns a renderable array for a asuntohaku page.
   */
  public function haso() {
    $build = [
      '#markup' => '<div id="asu_react_search"></div>',
      '#attached' => [
        'library' => 'asu_apartment_search/haso-apartment-search',
      ],
    ];
    return $build;
  }

  /**
   * Returns a renderable array for a asuntohaku page.
   */
  public function hasoUpcoming() {
    $build = [
      '#markup' => '<div id="asu_react_search"></div>',
      '#attached' => [
        'library' => 'asu_apartment_search/haso-apartment-upcoming',
      ],
    ];
    return $build;
  }

}
