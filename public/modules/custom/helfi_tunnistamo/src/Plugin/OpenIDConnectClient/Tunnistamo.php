<?php

declare(strict_types = 1);

namespace Drupal\helfi_tunnistamo\Plugin\OpenIDConnectClient;

use Drupal\openid_connect\Plugin\OpenIDConnectClientBase;

/**
 * Implements OpenID Connect Client plugin for Tunnistamo.
 *
 * @OpenIDConnectClient(
 *   id = "tunnistamo",
 *   label = @Translation("Tunnistamo")
 * )
 */
final class Tunnistamo extends OpenIDConnectClientBase {

  /**
   * {@inheritdoc}
   */
  public function getEndpoints() {
    return [
      'authorization' => 'https://api.hel.fi/sso/openid/authorize/',
      'token' => 'https://api.hel.fi/sso/openid/token/',
      'userinfo' => 'https://api.hel.fi/sso/openid/userinfo/',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getClientScopes() : array {
    return [
      'openid',
      'email',
      'ad_groups',
    ];
  }

}
