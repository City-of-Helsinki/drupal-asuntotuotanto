<?php

namespace Drupal\asu_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\samlauth\Controller\ExecuteInRenderContextTrait;
use Drupal\asu_user\SamlService;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Returns responses for asu_user module routes.
 */
class SamlController extends ControllerBase {

  use ExecuteInRenderContextTrait;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * SamlController constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The Renderer service.
   *
   **/
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer')
    );
  }

  /**
   * Initiates a SAML2 authentication flow.
   *
   * This route does not log us in (yet); it should redirect to the Login
   * service on the IdP, which should be redirecting back to our ACS endpoint
   * after authenticating the user.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   The HTTP response to send back.
   */
  public function login() {
    $function = function () {
      /** @var SamlService $saml */
      $saml = \Drupal::service('samlauth.asu_user');
      return $saml->login();
    };
    // This response redirects to an external URL in all/common cases. We count
    // on the routing.yml to specify that it's not cacheable.
    return $this->getShortenedRedirectResponse($function, 'initiating SAML login', '<front>');

    //echo 'login'; exit;
  }

  public function login_redirect() {
    \Drupal::messenger()->addMessage('tuleeko viesti tähän?');
  }

  /**
   * Gets a redirect response and modifies it a bit.
   *
   * Split off from getTrustedRedirectResponse() because that's in a trait.
   *
   * @param callable $callable
   *   Callable.
   * @param string $while
   *   Description of when we're doing this, for error logging.
   * @param string $redirect_route_on_exception
   *   Drupal route name to redirect to on exception.
   */
  protected function getShortenedRedirectResponse(callable $callable, $while, $redirect_route_on_exception) {
    $response = $this->getTrustedRedirectResponse($callable, $while, $redirect_route_on_exception);
    // Symfony RedirectResponses set a HTML document as content, which is going
    // to be ugly with our long URLs. Almost noone sees this content for a
    // HTTP redirect, but still: overwrite it with a similar HTML document that
    // doesn't include the URL parameter blurb in the rendered parts.
    $url = $response->getTargetUrl();
    $pos = strpos($url, '?');
    $shortened_url = $pos ? substr($url, 0, $pos) : $url;
    // Almost literal copy from RedirectResponse::setTargetUrl():
    $response->setContent(
      sprintf('<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="refresh" content="0;url=%1$s" />

        <title>Redirecting to %2$s</title>
    </head>
    <body>
        Redirecting to <a href="%1$s">%2$s</a>.
    </body>
</html>', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), $shortened_url));

    return $response;
  }

}
