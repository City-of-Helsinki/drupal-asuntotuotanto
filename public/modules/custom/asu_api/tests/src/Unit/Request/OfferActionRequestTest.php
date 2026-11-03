<?php

namespace Drupal\Tests\asu_api\Unit\Request;

use Drupal\asu_api\Api\BackendApi\Request\OfferActionRequest;
use Drupal\Tests\UnitTestCase;
use Drupal\user\Entity\User;

/**
 * Tests OfferActionRequest configuration.
 *
 * @group asu_api
 */
final class OfferActionRequestTest extends UnitTestCase {

  /**
   * Verifies path interpolation, method, auth flag, and payload shape.
   */
  public function testOfferActionRequest(): void {
    $user = $this->createMock(User::class);
    $request = new OfferActionRequest($user, 42, 'accepted');

    $this->assertSame('PATCH', $request->getMethod());
    $this->assertSame('/v1/profiles/me/offers/42/', $request->getPath());
    $this->assertTrue($request->requiresAuthentication());
    $this->assertSame(['state' => 'accepted'], $request->toArray());
    $this->assertSame($user, $request->getSender());
  }

}
