<?php

namespace Drupal\asu_api\Api\BackendApi\Service;

use Drupal\asu_api\Api\BackendApi\Request\AuthenticationRequest;
use Drupal\asu_api\Api\BackendApi\Response\AuthenticationResponse;
use Drupal\asu_api\Api\ClientFactory;
use Drupal\asu_api\Api\RequestHandler;
use Drupal\user\UserInterface;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Service handling user authentication.
 */
class AuthenticationService {

  private const TOKEN_KEY = 'asu_token';

  /**
   * User session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  private Session $session;

  private Client $client;

  /**
   * Constructor.
   *
   * AuthenticationService constructor.
   *
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   User session.
   */
  public function __construct(Client $client) {
    $this->client = $client;
  }

  /**
   * Handles user authentication.
   *
   * @param \Drupal\user\UserInterface $user
   *   Current user.
   *
   * @return string
   *   Authentication token.
   */
  public function handleAuthentication(UserInterface $user, Session $session): ?string {
    if (!$this->hasValidAuthToken($user, $session)) {
      try {
        $authenticationResponse = $this->authenticate($user);
        $session->set(self::TOKEN_KEY, $authenticationResponse->getToken());
        return $authenticationResponse->getToken();
      }
      catch (\Exception $e) {
        // @todo: Token is not set and authentication failed. Emergency.
        \Drupal::messenger()->addMessage('exception: ' . $e->getMessage());
        // Token is not set and authentication failed. Emergency.
        return NULL;
      }
    }
    return $session->get(self::TOKEN_KEY);
  }

  /**
   * Check if user has a valid token for backend api.
   *
   * @param \Drupal\user\UserInterface $user
   *   Current user.
   *
   * @return bool
   *   Is user able to send authenticated requests to backend.
   */
  private function hasValidAuthToken(UserInterface $user, Session $session): bool {
    if ($token = $session->get(self::TOKEN_KEY)) {
      return $this->isTokenAlive($token);
    }
    return FALSE;
  }

  /**
   * Check if token is active.
   *
   * @param string $token
   *   Users authentication token.
   *
   * @return bool
   *   Is token still usable.
   */
  private function isTokenAlive(string $token): bool {
    $token = explode(',', base64_decode($token));

    foreach ($token as $key => $value) {
      if (strpos($value, 'exp') !== FALSE) {
        $int = (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);

        return strtotime('now') < $int;
      }
    }

    return FALSE;
  }

  /**
   * Fetch new token.
   *
   * @param \Drupal\user\UserInterface $user
   *   Current user.
   *
   * @return \Drupal\asu_api\Api\BackendApi\Response\AuthenticationResponse
   *   Authentication response.
   *
   * @throws \Drupal\asu_api\Exception\RequestException
   */
  private function authenticate(UserInterface $user): AuthenticationResponse {
    $request = new AuthenticationRequest($user);
    $response = $this->client->post($request->getPath(), $request->toArray());
    return AuthenticationResponse::createFromHttpResponse($response);
  }

  /**
   * Get user token.
   */
  public function getUserToken(): string {
    return $this->session->get(self::TOKEN_KEY);
  }

}
