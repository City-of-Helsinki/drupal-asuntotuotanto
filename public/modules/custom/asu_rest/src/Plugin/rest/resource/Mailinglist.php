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
 *     "canonical" = "/project/mailinglist",
 *     "create" = "/project/mailinglist"
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
      'project_id',
    ];

    foreach ($required as $field) {
      if ($parameters->get($field)) {
        continue;
      }
      throw new BadRequestHttpException(sprintf('Missing required field: %s.', $field));
    }

    $project_id = 1;

    if (!filter_var($project_id, FILTER_VALIDATE_INT)) {
      throw new UnprocessableEntityHttpException('Given project id is not valid.');
    }

    $headers = getenv('APP_ENV') == 'testing' ? [
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => '*',
      'Access-Control-Allow-Headers' => '*',
    ] : [];

    $response = ['follow' => TRUE];

    // @todo Create logic, this task is not yet defined properly.
    // Check that project exists and premarketing end time < now.
    // Add user id & project id in database.
    // Create view which shows mailinglist subscriptions by project.
    return new ModifiedResourceResponse($response, 200, $headers);
  }

}
