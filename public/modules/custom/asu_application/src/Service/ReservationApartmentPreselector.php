<?php

namespace Drupal\asu_application\Service;

use Drupal\Component\Utility\UrlHelper;

/**
 * Resolves the apartment preselected for a HITAS post-period reservation.
 *
 * The search UI links to the application form with an "apartment" query
 * parameter so the customer does not have to pick the apartment again. The
 * requested apartment is only accepted when it is one of the apartments the
 * form actually offers, which keeps reserved and sold apartments out.
 */
final class ReservationApartmentPreselector {

  /**
   * Build an apartment field value for the requested apartment.
   *
   * @param string|null $requestedApartmentId
   *   Apartment node id from the request, or NULL when not given.
   * @param array $availableApartments
   *   Selectable apartments keyed by node id, values are the option labels.
   *
   * @return array|null
   *   Apartment field value with "id" and "information" keys, or NULL when the
   *   apartment cannot be preselected.
   */
  public function resolve(?string $requestedApartmentId, array $availableApartments): ?array {
    if ($requestedApartmentId === NULL || !ctype_digit($requestedApartmentId)) {
      return NULL;
    }

    if (!isset($availableApartments[$requestedApartmentId])) {
      return NULL;
    }

    return [
      'id' => $requestedApartmentId,
      'information' => $availableApartments[$requestedApartmentId],
    ];
  }

  /**
   * Append a validated apartment query parameter to a form URL.
   *
   * The add-form flow creates a draft application and redirects to its edit
   * form. The apartment id from the search UI must survive that redirect so
   * the edit form can still preselect it.
   *
   * @param string $url
   *   Destination URL, with or without an existing query string.
   * @param string|null $apartmentId
   *   Apartment node id from the current request, or NULL when not given.
   *
   * @return string
   *   URL with the apartment query parameter when the id is valid.
   */
  public function appendApartmentQuery(string $url, ?string $apartmentId): string {
    if ($apartmentId === NULL || !ctype_digit($apartmentId)) {
      return $url;
    }

    $parts = UrlHelper::parse($url);
    $query = $parts['query'] ?? [];
    $query['apartment'] = $apartmentId;

    $result = $parts['path'] ?? $url;
    if ($query) {
      $result .= '?' . UrlHelper::buildQuery($query);
    }
    if (!empty($parts['fragment'])) {
      $result .= '#' . $parts['fragment'];
    }

    return $result;
  }

}
