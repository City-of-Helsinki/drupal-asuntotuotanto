<?php

namespace Drupal\asu_api\Api\BackendApi;

use Drupal\asu_api\Api\BackendApi\Request\AuthenticationRequest;
use Drupal\asu_api\Api\Request;
use Drupal\asu_api\Api\Response;
use Drupal\asu_api\Exception\IllegalApplicationException;
use Drupal\asu_api\Helper\ApplicationHelper;
use Drupal\asu_api\Helper\AuthenticationHelper;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\user\UserInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\TransferStats;
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
   * @throws \Drupal\asu_api\Exception\IllegalApplicationException
   */
  public function send(Request $request, array $options = []): ?Response {
    $options['headers'] = $options['headers'] ?? [];
    if ($request->requiresAuthentication()) {
      if ($token = $this->handleAuthentication($request->getSender())) {
        $options['headers']['Authorization'] = sprintf("Bearer %s", $token);
      }
      else {
        throw new \InvalidArgumentException('Cannot authenticate request sender.');
      }
    }

    $logger = $this->logger;
    try {
      $response = $this->client->send(
        new GuzzleRequest(
          $request->getMethod(),
          $this->client->getConfig()['base_url'] . $request->getPath(),
          $options['headers'],
          json_encode($request->toArray())
        ),
        array_merge($options, [
          'on_stats' => function (TransferStats $stats) use ($logger, $request) {
            $time = $stats->getTransferTime();
            if ($time > 5) {
              $logger->critical(
                sprintf('A request took %s seconds. %s', $time, get_class($request))
              );
            }
          },
        ])
      );
      return $request::getResponse($response);
    }
    catch (\Exception $e) {
      $this->handleRequestError($e, $request);
    }

    return NULL;
  }

  /**
   * Return token from session or authenticate user.
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
        return FALSE;
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
    $logger = $this->logger;
    try {
      $response = $this->client->send(
        new GuzzleRequest(
          $request->getMethod(),
          $this->client->getConfig()['base_url'] . $request->getPath(),
          ['Content-Type' => 'application/json'],
          json_encode($request->toArray())
        ),
        [
          'on_stats' => function (TransferStats $stats) use ($logger, $request) {
            $time = $stats->getTransferTime();
            if ($time > 5) {
              $logger->critical(
                sprintf('A request took %s seconds. %s', $time, get_class($request))
              );
            }
          },
        ]
      );
    }
    catch (\Exception $e) {
      $this->handleRequestError($e, $request);
    }

    return $request::getResponse($response);
  }

  /**
   * Handle exceptions thrown by guzzle.
   *
   * @param \Exception $e
   *   The exception.
   * @param \Drupal\asu_api\Api\Request $request
   *   The request.
   *
   * @throws \Drupal\asu_api\Exception\IllegalApplicationException
   */
  private function handleRequestError(\Exception $e, Request $request) {
    switch (TRUE) {
      case $e instanceof ServerException:
        $this->handle500($e, $request);
        break;

      case $e instanceof ClientException:
        $this->handle400($e, $request);
        break;

      case $e instanceof ConnectException:
        $this->handleConnectionException($e, $request);
        break;

      default:
        $this->logError($e, $request);
    }
  }

  /**
   * Handle error on API server side, should not happen.
   *
   * @param \GuzzleHttp\Exception\ServerException $e
   *   Exception with error code 500.
   * @param \Drupal\asu_api\Api\Request $request
   *   The request.
   */
  private function handle500(ServerException $e, Request $request): void {
    $this->logError($e, $request);
    throw $e;
  }

  /**
   * Handle errors caused by bad requests.
   *
   * @param \GuzzleHttp\Exception\ClientException $e
   *   Exception with error code 400.
   * @param \Drupal\asu_api\Api\Request $request
   *   The request.
   *
   * @throws \Drupal\asu_api\Exception\IllegalApplicationException
   */
  private function handle400(ClientException $e, Request $request): void {
    $this->logError($e, $request);

    // 400 errors may contain custom error code.
    // @todo Currently api only gives error code on application errors.
    $message = ApplicationHelper::parseErrorCode($e);
    if (is_array($message) && isset($message['code'])) {
      throw new IllegalApplicationException(
        $message,
        (int) $e->getCode()
      );
    }
  }

  /**
   * Write to log.
   *
   * @param \GuzzleHttp\Exception\RequestException $e
   *   The exception.
   * @param \Drupal\asu_api\Api\Request $request
   *   The request.
   */
  private function logError(RequestException $e, Request $request): void {
    $httpCode = $e->getCode();
    $this->logger->emergency(
      sprintf(
        'Error %s: %s: %s',
        $httpCode,
        get_class($request),
        $e->getMessage()
      ));
    throw $e;
  }

  /**
   * Handle connection exception.
   *
   * @param \GuzzleHttp\Exception\ConnectException $e
   *   The exception.
   * @param \Drupal\asu_api\Api\Request $request
   *   The request.
   */
  private function handleConnectionException(ConnectException $e, Request $request): void {
    $this->logger->emergency(
      sprintf(
        'Error with connection: %s. %s',
        get_class($request),
        $e->getMessage()
      ));
    throw $e;
  }

}
