<?php

namespace Drupal\asu_editorial\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Drupal\user\Entity\User;

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
    $user = User::load(\Drupal::currentUser()->id());

    if ($route_match->getRouteName() == 'entity.user.canonical') {
      if ($user->hasRole('customer')) {
        return 'asuntotuotanto';
      }
    }

    return FALSE;
  }

}
