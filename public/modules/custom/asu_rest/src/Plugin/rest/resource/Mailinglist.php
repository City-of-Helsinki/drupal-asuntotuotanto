<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\Console\Command\Shared\TranslationTrait;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a resource to subscribe to mailing list.
 *
 * @RestResource(
 *   id = "mailinglist_rest_resource",
 *   label = @Translation("Mailinglist"),
 *   uri_paths = {
 *     "canonical" = "/mailinglist",
 *     "https://www.drupal.org/link-relations/create" = "/mailinglist"
 *   }
 * )
 */
final class Mailinglist extends ResourceBase {
  use TranslationTrait;

  /**
   * Responds to POST requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The HTTP response object.
   */
  public function post(array $subscription_data = []): ModifiedResourceResponse {
    /** @var ParameterBag $parameters */
    $parameters = new ParameterBag($subscription_data);
    $required = [
      'user_email',
      'project_id',
    ];

    foreach ($required as $field) {
      if ($parameters->get($field)) {
        continue;
      }
      throw new BadRequestHttpException(sprintf('Missing required field: %s.', $field));
    }

    // TODO: create logic.

    return new ModifiedResourceResponse('OK', 200);
  }

}
