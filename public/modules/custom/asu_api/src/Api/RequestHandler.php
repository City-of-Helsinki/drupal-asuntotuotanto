<?php

namespace Drupal\asu_api\Api;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Client;

/**
 * Handles requests.
 */
class RequestHandler {
  /**
   * Guzzle http-client.
   *
   * @var \GuzzleHttp\Client
   */
  private Client $client;

  /**
   * Api url.
   *
   * @var string
   */
  private string $apiUrl;

  /**
   * Options to set as guzzle request options.
   *
   * @var array
   */
  private $clientOptions;

  /**
   * Constructor.
   */
  public function __construct(string $apiUrl, array $clientOptions = []) {
    $this->client = \Drupal::httpClient();
    $this->apiUrl = $apiUrl;
    $this->clientOptions = $clientOptions;
  }

  /**
   * Send request.
   *
   * @param \GuzzleHttp\Psr7\RequestInterface $request
   *   Request.
   * @param array $options
   *   Options.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Response interface.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function send(RequestInterface $request, array $options = []): ResponseInterface {
    return $this->client->send($request, $options);
  }

  /**
   * Send http client post request.
   *
   * @param string $endpoint
   *   Api endpoint.
   * @param array $options
   *   Request options.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Http response.
   */
  public function post(string $endpoint, array $options): ResponseInterface {
    $url = $this->apiUrl . $endpoint;
    if (is_array($this->clientOptions) && !empty($this->clientOptions)) {
      $options += $this->clientOptions;
    }
    return $this->client->post($url, $options);
  }

  /**
   * Send http client get request.
   *
   * @param string $endpoint
   *   Api endpoint.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Http response.
   */
  public function get(string $endpoint): ResponseInterface {
    $url = $this->apiUrl . $endpoint;
    return $this->client->request('GET', $url, $this->clientOptions);
  }

  /**
   * Build request to be sent.
   *
   * @param \Drupal\asu_api\Api\Request $request
   *   Request.
   *
   * @return \GuzzleHttp\Psr7\RequestInterface
   *   Request to send.
   */
  public function buildGuzzleRequest(Request $request): RequestInterface {
    $method = $request->getMethod();
    $uri = "{$this->apiUrl}{$request->getPath()}";
    $payload = $request->toArray();
    return new Request(
      $method,
      $uri,
      ['Content-Type' => 'application/json'],
      json_encode($payload)
    );
  }

  /**
   * Build authenticated request.
   */
  public function buildAuthenticatedRequest(Request $request, string $token): RequestInterface {
    $method = $request->getMethod();
    $uri = "{$this->apiUrl}{$request->getPath()}";
    $payload = $request->toArray();
    $headers = [
      'Content-Type' => 'application/json',
      'Authorization' => sprintf("Bearer %s", $token),
    ];
    return new Request(
      $method,
      $uri,
      $headers,
      json_encode($payload)
    );
  }

}
