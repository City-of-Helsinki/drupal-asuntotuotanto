<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Provides a resource to subscribe to mailing list.
 *
 * @RestResource(
 *   id = "mailinglist",
 *   label = @Translation("Mailinglist"),
 *   uri_paths = {
 *     "canonical" = "/mailinglist",
 *     "https://www.drupal.org/link-relations/create" = "/mailinglist"
 *   }
 * )
 */
final class Mailinglist extends ResourceBase {

  /**
   * Responds to POST requests.
   *
   * @param array $data
   *   Mailinglist subscription data.
   *
   * @return Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   */
  public function post(array $data = []): ModifiedResourceResponse {
    /** @var \Symfony\Component\HttpFoundation\ParameterBag $parameters */
    $parameters = new ParameterBag($data);
    $required = [
      'user_email',
      'project_id',
      // 'subscribe_mailinglist',
    ];

    foreach ($required as $field) {
      if ($parameters->get($field)) {
        continue;
      }
      throw new BadRequestHttpException(sprintf('Missing required field: %s.', $field));
    }

    $email = $parameters->get('user_email');
    $project_id = $parameters->get('project_id');
    $subscribe = $parameters->get('subscribe_mailinglist');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new UnprocessableEntityHttpException('Given email address is not valid.');
    }

    if (!filter_var($project_id, FILTER_VALIDATE_INT)) {
      throw new UnprocessableEntityHttpException('Given project id is not valid.');
    }

    if (!filter_var($subscribe, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) {
      $subscribe = FALSE;
    }

    // @todo Create logic, this task is not yet defined properly.
    // Check that project exists and premarketing end time < now.
    // Add user id & project id in database.
    // Create view which shows mailinglist subscriptions by project.
    return new ModifiedResourceResponse('OK', 200);

  }

}
