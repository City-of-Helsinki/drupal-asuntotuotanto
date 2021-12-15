<?php

namespace Drupal\asu_api\Api\BackendApi;

use Drupal\asu_api\Api\BackendApi\Request\AuthenticationRequest;
use Drupal\asu_api\Api\Request;
use Drupal\asu_api\Api\Response;
use Drupal\asu_api\Helper\AuthenticationHelper;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as GuzzleRequest;

/**
 * Integration to django.
 */
class BackendApi {

  /**
   * Http client.
   *
   * @var \GuzzleHttp\Client
   */
  private Client $client;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * Tempstore.
   *
   * @var Drupal\Core\TempStore\PrivateTempStore
   */
  private PrivateTempStore $store;

  /**
   * Constructor.
   *
   * @param GuzzleHttp\Client $client
   *   Http client.
   * @param Psr\Log\LoggerInterface $logger
   *   Logger.
   */
  public function __construct(Client $client, LoggerInterface $logger) {
    $this->client = $client;
    $this->logger = $logger;
    $storeFactory = \Drupal::service('tempstore.private');
    $this->store = $storeFactory->get('customer');
  }

  /**
   * Send request.
   *
   * @param \Drupal\asu_api\Api\Request $request
   *   Request object.
   * @param array $options
   *   Request options.
   *
   * @return \Drupal\asu_api\Api\Response
   *   Response object.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function send(Request $request, array $options = []): ?Response {
    $options['headers'] = [];
    if ($request->requiresAuthentication()) {
      if ($token = $this->handleAuthentication($request->getSender())) {
        $options['headers']['Authorization'] = sprintf("Bearer %s", $token);
      }
      else {
        throw new \InvalidArgumentException('Cannot authenticate request sender.');
      }
    }

    try {
      $response = $this->client->send(
        new GuzzleRequest(
          $request->getMethod(),
          $this->client->getConfig()['base_url'] . $request->getPath(),
          $options['headers'],
          json_encode($request->toArray())
        ),
        $options['headers']
      );
      return $request::getResponse($response);
    }
    catch (\Exception $e) {
      $this->logger->emergency(sprintf('%s failed: %s', get_class($request), $e->getMessage()));
      throw $e;
    }
  }

  /**
   * Handle backend api authentication.
   *
   * @param \Drupal\user\UserInterface $account
   *   User who is the sender of the request.
   *
   * @return string|null
   *   Authentication token.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function handleAuthentication(UserInterface $account): ?string {
    $token = $this->store->get('asu_api_token');
    if (!$token || !AuthenticationHelper::isTokenAlive($token)) {
      try {
        $authenticationResponse = $this->authenticate($account);
        $this->store->set('asu_api_token', $authenticationResponse->getToken());
        return $authenticationResponse->getToken();
      }
      catch (\Exception $e) {
        $this->logger->critical('Exception during backend authentication: ' . $e->getMessage());
        throw $e;
      }
    }
    return $token;
  }

  /**
   * Send authentication request.
   *
   * @param Drupal\user\UserInterface $user
   *   Customer class.
   *
   * @return \Drupal\asu_api\Api\Response
   *   Response class.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function authenticate(UserInterface $user): Response {
    $request = new AuthenticationRequest($user);
    $response = $this->client->send(
      new GuzzleRequest(
        $request->getMethod(),
        $this->client->getConfig()['base_url'] . $request->getPath(),
        ['Content-Type' => 'application/json'],
        json_encode($request->toArray())
      )
    );
    return $request::getResponse($response);
  }

}
