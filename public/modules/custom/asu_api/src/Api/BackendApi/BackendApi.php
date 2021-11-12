<?php

namespace Drupal\asu_api\Api\BackendApi;

use Drupal\asu_api\Api\BackendApi\Request\AuthenticationRequest;
use Drupal\asu_api\Api\Request;
use Drupal\asu_api\Api\Response;
use Drupal\asu_user\Customer;
use Drupal\Core\Logger\LoggerChannel;
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
  private LoggerChannel $logger;

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
      if ($token = $this->handleAuthentication()) {
        $options['headers']['Authorization'] = sprintf("Bearer %s", $token);
      }
    }

    try {
      $return = $this->client->send(
        new GuzzleRequest(
          $request->getMethod(),
          $this->client->getConfig()['base_url'] . $request->getPath(),
          $options['headers'],
          json_encode($request->toArray())
        ),
        $options['headers']
      );
    }
    catch (\Exception $e) {
      $this->logger->emergency(sprintf('%s failed: %s', get_class($request), $e->getMessage()));
      throw $e;
    }

    return $request::getResponse($return);
  }

  /**
   * Make sure user is authenticated.
   *
   * @return string|null
   *   Authentication token.
   */
  private function handleAuthentication(): ?string {
    $customer = \Drupal::service('asu_user.customer');
    if (!$customer->hasValidAuthToken()) {
      try {
        $authenticationResponse = $this->authenticate($customer);
        $customer->setToken($authenticationResponse->getToken());
        return $authenticationResponse->getToken();
      }
      catch (\Exception $e) {
        // @todo Token is not set and authentication failed, Emergency.
        \Drupal::messenger()->addMessage('exception: ' . $e->getMessage());
        // Token is not set and authentication failed. Emergency.
        return NULL;
      }
    }
    return $customer->getToken();
  }

  /**
   * Send authentication request.
   *
   * @param \Drupal\asu_user\Customer $customer
   *   Customer class.
   *
   * @return \Drupal\asu_api\Api\Response
   *   Response class.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function authenticate(Customer $customer): Response {
    $request = new AuthenticationRequest($customer);
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
