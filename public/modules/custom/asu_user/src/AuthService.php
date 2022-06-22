<?php

namespace Drupal\asu_user;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Drupal\Component\Utility\Crypt;
use Drupal\samlauth\SamlService;
use Drupal\samlauth\UserVisibleException;
use Drupal\user\Entity\User;

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
    }
    if (!isset($acs_exception)) {
      $unique_id = $this->getAttributeByConfig('unique_id_attribute');
      $unique_id = $this->hashPid($unique_id);

      if ($unique_id) {
        $account = $this->externalAuth->load($unique_id, 'samlauth') ?: NULL;
      }
    }

    $logout_different_user = $config->get('logout_different_user');
    if ($this->currentUser->isAuthenticated()) {
      // Either redirect or log out so that we can log a different user in.
      // 'Redirecting' is done by the caller - so we can just return from here.
      if (isset($account) && $account->id() === $this->currentUser->id()) {
        // Noting that we were already logged in probably isn't useful. (Core's
        // user/reset link isn't a good case to compare: it always logs the
        // user out and presents the "Reset password" form with a login button.
        // 'drush uli' links, at least on D7, display an info message "please
        // reset your password" because they land on the user edit form.)
        return !isset($acs_exception);
      }
      if (!$logout_different_user) {
        // Message similar to when a user/reset link is followed.
        $this->messenger->addWarning($this->t('Another user (%other_user) is already logged into the site on this computer, but you tried to log in as user %login_user through an external authentication provider. Please <a href=":logout">log out</a> and try again.', [
          '%other_user' => $this->currentUser->getAccountName(),
          '%login_user' => $account ? $account->getAccountName() : '?',
          // Point to /user/logout rather than /saml/logout because we don't
          // want to make people log out from all their logged-in sites.
          ':logout' => Url::fromRoute('user.logout')->toString(),
        ]));
        return !isset($acs_exception);
      }
      // If the SAML response indicates (/ if the processing generated) an
      // error, we don't want to log the current user out but we want to
      // clearly indicate that someone else is still logged in.
      if (isset($acs_exception)) {
        $this->messenger->addWarning($this->t('Another user (%other_user) is already logged into the site on this computer. You tried to log in through an external authentication provider, which failed, so the user is still logged in.', [
          '%other_user' => $this->currentUser->getAccountName(),
        ]));
      }
    }

    if (isset($acs_exception)) {
      $this->flood->register('samlauth.failed_login_ip', $flood_config->get('ip_window'));
      throw $acs_exception;
    }
    if (!$unique_id) {
      throw new \RuntimeException('Configured unique ID is not present in SAML response.');
    }

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
      // Check that current user and loaded user match.
      if ($this->currentUser->isAuthenticated()) {
        // Load current user object.
        $user = User::load(\Drupal::currentUser()->id());
        // Get unique attribute.
        $pid = $this->getAttributeByConfig('unique_id_attribute');
        // Crypt unique attribute.
        $pid = $this->hashPid($pid);
        // Search user by hash.
        $query = $this->entityTypeManager->getStorage('user')->getQuery();
        $query->condition('field_saml_hash', $pid);
        $results = $query->execute();
        $count = count($results);
        // Get user is valid value.
        $is_valid = (int) $user->get('field_email_is_valid')->getValue()['value'];

        // If user not validate.
        if ($is_valid == 0 && $count == 0) {
          // Set valid value.
          $user->set('field_email_is_valid', 1);
          // Set unique value.
          $user->set('field_saml_hash', $pid);
          // Save values to user.
          $user->save();
          // Use loaded user to account.
          $account = $user;
          // Link authentication.
          $this->linkExistingAccount($unique_id, $account);
          $first_saml_login = TRUE;
        }
        else {
          throw new UserVisibleException('Account is already authenticated.');
        }
      }
      else {
        throw new UserVisibleException('You need to be login in to the site first.');
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
   * @param string $hash
   *
   * @return string $hash
   */
  protected function hashPid($hash) {
    $hash_key = getenv('ASU_HASH_KEY');
    $hash = Crypt::hmacBase64($hash, $hash_key);

    return $hash;
  }

}
