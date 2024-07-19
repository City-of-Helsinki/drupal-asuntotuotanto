<?php

namespace Drupal\asu_api\Api;

use Drupal\user\UserInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Custom request class.
 */
abstract class Request {
  protected const AUTHENTICATED = FALSE;
  protected const METHOD = 'GET';
  protected const PATH = '';

  /**
   * The user that will be sender of the request.
   *
   * @var Drupal\user\UserInterface|null
   */
  protected ?UserInterface $sender;

  /**
   * Gets the HTTP method.
   *
   * @return string
   *   The HTTP method.
   */
  public function getMethod(): string {
    return static::METHOD;
  }

  /**
   * Gets the request path.
   *
   * For example /prices.
   *
   * @param array $queryparameters
   *   Optional query parameters.
   *
   * @return string
   *   The path.
   */
  public function getPath(array $queryparameters = []): string {
    if (!static::PATH) {
      throw new \LogicException('Missing path.');
    }
    return static::PATH;
  }

  /**
   * Set sender.
   *
   * @param Drupal\user\UserInterface $sender
   *   The user sending the request.
   */
  public function setSender(UserInterface $sender) {
    $this->sender = $sender;
  }

  /**
   * Get the user sending the request.
   */
  public function getSender(): ?UserInterface {
    return $this->sender;
  }

  /**
   * Gets the request data.
   *
   * @return array
   *   The data to send with the request.
   */
  public function toArray(): array {
    return [];
  }

  /**
   * Does endpoint require auth token.
   *
   * @return bool
   *   Endpoint requires authentication.
   */
  public function requiresAuthentication(): bool {
    return static::AUTHENTICATED;
  }

  /**
   * Create custom response object for http client response.
   *
   * @param Psr\Http\Message\ResponseInterface $response
   *   Guzzle request to map.
   *
   * @return Drupal\asu_api\Response
   *   Custom response class.
   */
  abstract public static function getResponse(ResponseInterface $response): Response;

}
