<?php

namespace Drupal\asu_user;

use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Drupal\Component\Utility\Crypt;
use Drupal\samlauth\SamlService;
use Drupal\samlauth\UserVisibleException;
use Drupal\asu_api\Api\BackendApi\Request\CreateUserRequest;

/**
 * Governs communication between the SAML toolkit and the IdP / login behavior.
 *
 * There's no formal interface here, only a promise to not change things in
 * breaking ways in the 3.x releases. The division in responsibilities between
 * this class and SamlController (which calls most of its public methods) is
 * partly arbitrary. It's roughly "Controller contains code dealing with
 * redirects; SamlService contains the other logic". Code will likely be moved
 * around to new classes in 4.x.
 */
class AuthService extends SamlService {

  /**
   * Processes a SAML response (Assertion Consumer Service).
   *
   * First checks whether the SAML request is OK, then takes action on the
   * Drupal user (logs in / maps existing / create new) depending on attributes
   * sent in the request and our module configuration.
   *
   * @return bool
   *   TRUE if the response was correctly processed; FALSE if an error was
   *   encountered while processing but there's a currently logged-in user and
   *   we decided not to throw an exception for this case.
   *
   * @throws \Exception
   */
  public function acs() {
    $config = $this->configFactory->get('samlauth.authentication');
    if ($config->get('debug_log_in')) {
      if (isset($_POST['SAMLResponse'])) {
        $response = base64_decode($_POST['SAMLResponse']);
        if ($response) {
          $this->logger->debug("ACS received 'SAMLResponse' in POST request (base64 decoded): <pre>@message</pre>", ['@message' => $response]);
        }
        else {
          $this->logger->warning("ACS received 'SAMLResponse' in POST request which could not be base64 decoded: <pre>@message</pre>", ['@message' => $_POST['SAMLResponse']]);
        }
      }
      else {
        // Not sure if we should be more detailed...
        $this->logger->warning("HTTP request to ACS is not a POST request, or contains no 'SAMLResponse' parameter.");
      }
    }

    // Perform flood control. This is not to guard against failed login
    // attempts per se; that is the IdP's job. It's just protection against
    // a flood of bogus (DDoS-like) requests because this route performs
    // computationally expensive operations. So: just IP based flood control,
    // using the limit / window values that Core uses for regular login.
    $flood_config = $this->configFactory->get('user.flood');
    if (!$this->flood->isAllowed('samlauth.failed_login_ip', $flood_config->get('ip_limit'), $flood_config->get('ip_window'))) {
      throw new TooManyRequestsHttpException(NULL, 'Access is blocked because of IP based flood prevention.');
    }

    // Process the ACS response message and check if we can derive a linked
    // account, but don't process errors yet. (The following code is a kludge
    // because we may need the linked account / may ignore errors later.)
    try {
      $this->processLoginResponse();
    }
    catch (\Exception $acs_exception) {
      $this->messenger->addWarning($this->t('You have canceled authentication process', [
        '%other_user' => $this->currentUser->getAccountName(),
      ]));

      $this->flood->register('samlauth.failed_login_ip', $flood_config->get('ip_window'));

      return FALSE;
    }

    $unique_id = $this->getAttributeByConfig('unique_id_attribute');
    $unique_id = $this->hashPid($unique_id);

    if (!$unique_id) {
      $this->messenger->addError(
        $this->t('Authentication has has failed. ID is not present in SAML response.')
      );

      return FALSE;
    }

    $account = $this->externalAuth->load($unique_id, 'samlauth') ?: NULL;
    $this->doLogin($unique_id, $account);

    // Remember SAML session values that may be necessary for logout.
    $auth = $this->getSamlAuth('acs');
    $values = [
      'session_index' => $auth->getSessionIndex(),
      'session_expiration' => $auth->getSessionExpiration(),
      'name_id' => $auth->getNameId(),
      'name_id_format' => $auth->getNameIdFormat(),
    ];
    foreach ($values as $key => $value) {
      if (isset($value)) {
        $this->privateTempStore->set($key, $value);
      }
      else {
        $this->privateTempStore->delete($key);
      }
    }

    return TRUE;
  }

  /**
   * Logs a user in, creating / linking an account; synchronizes attributes.
   *
   * Split off from acs() to... have at least some kind of split.
   *
   * @param string $unique_id
   *   The unique ID (attribute value) contained in the SAML response.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The existing user account derived from the unique ID, if any.
   */
  protected function doLogin($unique_id, AccountInterface $account = NULL) {
    $config = $this->configFactory->get('samlauth.authentication');
    $first_saml_login = FALSE;

    if (!$account) {
      if ($config->get('create_users')) {
        $attributes = $this->getAttributes();
        $name = reset($attributes['displayName']);
        $mail = (isset($attributes['mail'])) ? reset($attributes['mail']) : NULL;

        $account_data = [
          'mail' => $mail,
          'name' => $name,
          'type' => 'customer',
          'langcode' => 'fi',
          'field_email_is_valid' => 0,
          'field_saml_hash' => $unique_id,
        ];

        $account = $this->externalAuth->register($unique_id, 'samlauth', $account_data);
        $this->externalAuth->userLoginFinalize($account, $unique_id, 'samlauth');

        $pid = $this->getAttributeByConfig('unique_id_attribute');
        $lastname = reset($attributes['sn']) ?? NULL;
        $first_name = reset($attributes['firstName']) ?? NULL;
        $divider = substr($pid, 6, 1);
        $dividers = ['18' => '+', '19' => '-', '20' => 'A'];
        $century = array_search($divider, $dividers);
        $year = $century . substr($pid, 4, 2);
        $day = substr($pid, 0, 2);
        $month = substr($pid, 2, 2);
        $birth_day = sprintf('%s.%s.%s', $day, $month, $year);

        $store = \Drupal::service('tempstore.private')
          ->get('customer');
        $store->set('first_name', $first_name);
        $store->set('last_name', $lastname);
        $store->set('date_of_birth', $birth_day);

        $accountData = [
          'first_name' => $first_name,
          'last_name' => $lastname,
          'date_of_birth' => $birth_day,
          'personal_identification_number' => $pid
        ];

        $request = new CreateUserRequest(
          $account,
          $accountData,
          'customer'
        );

        try {
          /** @var \Drupal\asu_api\Api\BackendApi\BackendApi $backendApi */
          $backendApi = \Drupal::service('asu_api.backendapi');
          $response = $backendApi->send($request);
          $account->field_backend_profile = $response->getProfileId();
          $account->field_backend_password = $response->getPassword();
          $account->save();
        }
        catch (\Exception $e) {
          \Drupal::logger('asu_backend_api')->emergency(
          'Exception while creating user to backend: ' . $e->getMessage()
          );
        }
      }
      else {
        throw new UserVisibleException('No existing user account matches the SAML ID provided. This authentication service is not configured to create new accounts.');
      }
    }

    // If we haven't found an account to link, create one from the SAML
    // attributes.
    if (!$account) {
      throw new UserVisibleException('No existing user account matches the SAML ID provided. This authentication service is not configured to create new accounts.');
    }
    elseif ($account->isBlocked()) {
      throw new UserVisibleException('Requested account is blocked.');
    }
    else {
      // Synchronize the user account with SAML attributes if needed.
      $this->synchronizeUserAttributes($account, FALSE, $first_saml_login);
      $this->externalAuth->userLoginFinalize($account, $unique_id, 'samlauth');
    }
  }

  /**
   * Crypt string.
   *
   * @param string $string
   *   String what is going to be crypted.
   *
   * @return string
   *   Crypted string.
   */
  protected function hashPid($string) {
    $hash_key = getenv('ASU_HASH_KEY');
    $hash = Crypt::hmacBase64($string, $hash_key);

    return $hash;
  }

}
