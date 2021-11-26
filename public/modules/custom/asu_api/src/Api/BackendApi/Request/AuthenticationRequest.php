<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Psr\Http\Message\ResponseInterface;
use Drupal\asu_api\Api\BackendApi\Response\AuthenticationResponse;
use Drupal\asu_api\Api\Request;
use Drupal\asu_user\Customer;

/**
 * Request authentication token.
 */
class AuthenticationRequest extends Request {
  protected const METHOD = 'POST';
  protected const PATH = '/v1/token/';
  protected const AUTHENTICATED = TRUE;

  /**
   * The customer.
   *
   * @var \Drupal\asu_user\Customer
   */
  private Customer $customer;

  /**
   * AuthenticationRequest constructor.
   *
   * @param \Drupal\asu_user\Customer $customer
   *   Current user.
   */
  public function __construct(Customer $customer) {
    $this->customer = $customer;
  }

  /**
   * Get user.
   *
   * @return \Drupal\asu_user\Customer
   *   Customer class.
   */
  public function getUser(): Customer {
    return $this->customer;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'profile_id' => $this->customer->getUserField('field_backend_profile'),
      'password' => $this->customer->getUserField('field_backend_password'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getResponse(ResponseInterface $response): AuthenticationResponse {
    return AuthenticationResponse::createFromHttpResponse($response);
  }

}
