<?php

namespace Drupal\asu_user\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Change samlauth acs to custom acs'.
    if ($route = $collection->get('samlauth.saml_controller_acs')) {
      $route->setDefaults(
        ['_controller' => '\Drupal\asu_user\Controller\AsuController::acs']
      );
    }

    // Deny all access to saml login route.
    if ($route = $collection->get('samlauth.saml_controller_login')) {
      $route->setDefaults(
        [$route->setRequirement('_access', 'FALSE')]
      );
    }
  }

}
