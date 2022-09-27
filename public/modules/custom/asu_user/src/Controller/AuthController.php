<?php

namespace Drupal\asu_user\Controller;

/**
 * @file
 * Contains \Drupal\asu_user\Controller\AuthController.
 */

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Token;
use Drupal\samlauth\Controller\ExecuteInRenderContextTrait;
use Drupal\samlauth\Controller\SamlController;
use Drupal\asu_user\AuthService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for asu_user module routes.
 */
class AuthController extends SamlController {

  use ExecuteInRenderContextTrait;

  /**
   * The samlauth SAML service.
   *
   * @var \Drupal\asu_user\AuthService
   */
  protected $saml;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The PathValidator service.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * SamlController constructor.
   *
   * @param \Drupal\asu_user\AuthService $saml
   *   The samlauth SAML service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The PathValidator service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The Renderer service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(AuthService $saml, RequestStack $request_stack, ConfigFactoryInterface $config_factory, PathValidatorInterface $path_validator, RendererInterface $renderer, Token $token, MessengerInterface $messenger, LoggerInterface $logger) {
    $this->saml = $saml;
    $this->requestStack = $request_stack;
    $this->configFactory = $config_factory;
    $this->pathValidator = $path_validator;
    $this->renderer = $renderer;
    $this->token = $token;
    $this->messenger = $messenger;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('asu_user.auth'),
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('path.validator'),
      $container->get('renderer'),
      $container->get('token'),
      $container->get('messenger'),
      $container->get('logger.channel.asu_user')
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
      global $base_url;
      $config = $this->configFactory->get('samlauth.authentication');
      $return_to = $config->get('login_redirect_url') ?? $base_url . '/user';

      return $this->saml->login($return_to);
    };
    // This response redirects to an external URL in all/common cases. We count
    // on the routing.yml to specify that it's not cacheable.
    return $this->getShortenedRedirectResponse($function, 'initiating SAML login', '<front>');
  }

  /**
   * Redirect to.
   */
  public function return() {
    if (\Drupal::currentUser()->isAnonymous()) {
      $route_name = 'user.login';
      $route_parameters = [];

      return $this->redirect($route_name, $route_parameters);
    }

    $route_name = 'entity.user.edit_form';
    $route_parameters = [
      'user' => \Drupal::currentUser()->id(),
    ];

    return $this->redirect($route_name, $route_parameters);
  }

}
