<?php

declare(strict_types=1);

namespace Drupal\asu_rest\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Flood\FloodInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to kernel events to enforce OAuth2 bruteforce flood protection.
 *
 * Protects:
 * - OAuth2 Bearer REST endpoints (asu_projects, asu_apartments, etc.).
 * - oauth/token endpoint.
 *
 * Drupal 11 dispatches REST resource requests as sub-requests, so
 * isMainRequest() is intentionally NOT checked here. The response is
 * set via setResponse() rather than throwing an exception, because
 * exceptions in sub-requests do not propagate to the outer response.
 */
final class OAuth2BruteforceFloodSubscriber implements EventSubscriberInterface {

  private const REST_FLOOD_EVENT = 'asu_rest.oauth2_bruteforce_ip';

  private const TOKEN_FLOOD_EVENT = 'asu_rest.oauth2_token_bruteforce_ip';

  private const TOKEN_ROUTE = 'oauth2_token.token';

  /**
   * Constructs a new OAuth2BruteforceFloodSubscriber.
   *
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private readonly FloodInterface $flood,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Run after RouterListener (32) so _route is available.
      KernelEvents::REQUEST => ['onRequest', 15],
      KernelEvents::RESPONSE => ['onResponse', -50],
    ];
  }

  /**
   * Checks flood before processing OAuth2 REST or token requests.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');

    // oauth/token endpoint: check flood (10/3600).
    if ($route_name === self::TOKEN_ROUTE) {
      $token_config = $this->configFactory->get('asu_rest.settings')->get('oauth2_token_flood') ?? [];
      $limit = (int) ($token_config['ip_limit'] ?? 10);
      $window = (int) ($token_config['ip_window'] ?? 3600);
      if (!$this->flood->isAllowed(self::TOKEN_FLOOD_EVENT, $limit, $window)) {
        $event->setResponse(new Response(
          'Access is blocked because of IP based flood prevention.',
          Response::HTTP_TOO_MANY_REQUESTS,
        ));
      }
      return;
    }

    // OAuth2 Bearer REST: only check if request has Bearer and route/path matches.
    if (!$this->hasBearerHeader($request) || !$this->isOauth2RestRoute($route_name, $request)) {
      return;
    }

    $rest_config = $this->configFactory->get('asu_rest.settings')->get('oauth2_rest_flood') ?? [];
    $limit = (int) ($rest_config['ip_limit'] ?? 5);
    $window = (int) ($rest_config['ip_window'] ?? 3600);
    if (!$this->flood->isAllowed(self::REST_FLOOD_EVENT, $limit, $window)) {
      $event->setResponse(new Response(
        'Access is blocked because of IP based flood prevention.',
        Response::HTTP_TOO_MANY_REQUESTS,
      ));
    }
  }

  /**
   * Registers flood on failed OAuth2 REST (401) or token (4xx/5xx) responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function onResponse(ResponseEvent $event): void {
    $route_name = $event->getRequest()->attributes->get('_route');
    $status = $event->getResponse()->getStatusCode();

    // oauth/token: register on 4xx or 5xx (failed token exchange).
    if ($route_name === self::TOKEN_ROUTE && $status >= 400) {
      $token_config = $this->configFactory->get('asu_rest.settings')->get('oauth2_token_flood') ?? [];
      $window = (int) ($token_config['ip_window'] ?? 3600);
      $this->flood->register(self::TOKEN_FLOOD_EVENT, $window);
      return;
    }

    // OAuth2 REST: register only on 401 with Bearer header.
    $request = $event->getRequest();
    if ($status === 401 && $this->hasBearerHeader($request) && $this->isOauth2RestRoute($route_name, $request)) {
      $rest_config = $this->configFactory->get('asu_rest.settings')->get('oauth2_rest_flood') ?? [];
      $window = (int) ($rest_config['ip_window'] ?? 3600);
      $this->flood->register(self::REST_FLOOD_EVENT, $window);
    }
  }

  /**
   * Checks if the request has an Authorization: Bearer header.
   */
  private function hasBearerHeader(Request $request): bool {
    $auth = $request->headers->get('Authorization', '');
    return str_starts_with($auth, 'Bearer ');
  }

  /**
   * Checks if the route name matches an OAuth2-protected REST resource.
   *
   * Also checks the request path as fallback when route is missing (e.g. 404).
   */
  private function isOauth2RestRoute(?string $route_name, Request $request): bool {
    if ($route_name !== NULL && str_starts_with($route_name, 'rest.')) {
      $rest_config = $this->configFactory->get('asu_rest.settings')->get('oauth2_rest_flood') ?? [];
      $resource_ids = (array) ($rest_config['resource_ids'] ?? []);
      foreach ($resource_ids as $id) {
        if (str_starts_with($route_name, 'rest.' . $id . '.')) {
          return TRUE;
        }
      }
    }
    // Path-based fallback: project/apartment list endpoints.
    $path = $request->getPathInfo();
    return str_contains($path, '/projects')
      || str_contains($path, '/apartments');
  }

}
