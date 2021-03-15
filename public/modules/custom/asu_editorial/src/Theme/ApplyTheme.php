<?php

namespace Drupal\asu_editorial\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * An ApplyTheme class.
 *
 * @package Drupal\asu_editorial\Theme
 */
class ApplyTheme implements ThemeNegotiatorInterface {

  /**
   * {@inheritDoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return $this->negotiateRoute($route_match) ? TRUE : FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    return $this->negotiateRoute($route_match) ?: NULL;
  }

  /**
   * Apply theme based on the route.
   *
   * {@inheritDoc}
   */
  private function negotiateRoute(RouteMatchInterface $route_match) {
    $theme = FALSE;

    if ($route_match->getRouteName() == 'entity.user.canonical') {
      $theme = 'asu_admin';
    }

    return $theme;
  }

}
