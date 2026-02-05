<?php

namespace Drupal\asu_api\Api\BackendApi;

use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\asu_api\Api\BackendApi\Request\AuthenticationRequest;
use Drupal\asu_api\Api\BackendApi\Request\DeleteApplicationRequest;
use Drupal\asu_api\Api\Request;
use Drupal\asu_api\Api\Response;
use Drupal\asu_api\Exception\IllegalApplicationException;
use Drupal\asu_api\Helper\AuthenticationHelper;
use Drupal\asu_api\Helper\RequestHelper;
use Drupal\user\UserInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\TransferStats;
use Psr\Log\LoggerInterface;

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
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   Temp store service.
   */
  public function __construct(Client $client, LoggerInterface $logger, PrivateTempStoreFactory $temp_store_factory) {
    $this->client = $client;
    $this->logger = $logger;
    $storeFactory = $temp_store_factory;
    $this->store = $storeFactory->get('customer');
  }

  /**
   * Performs the request to delete an application.
   */
  public function deleteApplication(UserInterface $sender, string $applicationId): void {
    $request = new DeleteApplicationRequest($sender, $applicationId);
    $this->send($request);
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
        // If sender authenticate fails shows error.
        throw new \InvalidArgumentException('Cannot authenticate request sender.');
      }
    }

    $options['timeout'] = 60;

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
    catch (RequestException $e) {
      $this->handleRequestError($e, $request);
    }
    catch (\Exception $e) {
      // Log non-RequestException errors and re-throw.
      $this->logger->error(
        sprintf('Unexpected error in API request: %s', $e->getMessage()),
        ['exception' => $e, 'request' => get_class($request)]
      );
      throw $e;
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
  private function handleAuthentication(?UserInterface $account = NULL): ?string {
    if ($account) {
      $token = $this->store->get('asu_api_token');
    }
    else {
      $token = getenv('DRUPAL_SERVER_AUTH_TOKEN');
    }

    if ($account && (!$token || !AuthenticationHelper::isTokenAlive($token))) {
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
          'timeout' => 60,
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
    catch (RequestException|ConnectException $e) {
      $this->handleRequestError($e, $request);
    }
    catch (\Exception $e) {
      throw $e;
    }

    return $request::getResponse($response);
  }

  /**
   * Handle exceptions thrown by guzzle.
   *
   * @param \GuzzleHttp\Exception\RequestException|\GuzzleHttp\Exception\ConnectException $e
   *   The exception.
   * @param \Drupal\asu_api\Api\Request $request
   *   The request.
   *
   * @throws \Drupal\asu_api\Exception\IllegalApplicationException
   */
  private function handleRequestError(RequestException|ConnectException $e, Request $request) {
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
    throw $e;
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
    $message = RequestHelper::parseErrorCode($e);
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
