<?php

namespace Drupal\asu_user;

use Drupal\asu_api\Api\BackendApi\BackendApi;
use Drupal\asu_api\Api\BackendApi\Request\CreateUserRequest;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\externalauth\Authmap;
use Drupal\externalauth\ExternalAuth;
use Drupal\samlauth\SamlService;
use Drupal\samlauth\UserVisibleException;
use OneLogin\Saml2\Utils as SamlUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

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
   * {@inheritDoc}
   */
  public function __construct(
    ExternalAuth $external_auth,
    Authmap $authmap,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger,
    EventDispatcherInterface $event_dispatcher,
    RequestStack $request_stack,
    PrivateTempStoreFactory $temp_store_factory,
    FloodInterface $flood,
    AccountInterface $current_user,
    MessengerInterface $messenger,
    TranslationInterface $translation,
    BackendApi $backendApi,
    Connection $database,
  ) {
    $this->externalAuth = $external_auth;
    $this->authmap = $authmap;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
    $this->requestStack = $request_stack;
    $this->privateTempStore = $temp_store_factory->get('samlauth');
    $this->privateTempCustomer = $temp_store_factory->get('customer');
    $this->flood = $flood;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->setStringTranslation($translation);
    $this->backendApi = $backendApi;
    $this->database = $database;

    $config = $this->configFactory->get('samlauth.authentication');
    // setProxyVars lets the SAML PHP Toolkit use 'X-Forwarded-*' HTTP headers
    // for identifying the SP URL, but we should pass the Drupal/Symfony base
    // URL to into the toolkit instead. That uses headers/trusted values in the
    // same way as the rest of Drupal (as configured in settings.php).
    // @todo remove this in v4.x
    if ($config->get('use_proxy_headers') && !$config->get('use_base_url')) {
      // Use 'X-Forwarded-*' HTTP headers for identifying the SP URL.
      SamlUtils::setProxyVars(TRUE);
    }
  }

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
      if ($this->requestStack->getCurrentRequest()->request->get('SAMLResponse') !== NULL) {
        $response = base64_decode($this->requestStack->getCurrentRequest()->request->get('SAMLResponse'));
        if ($response) {
          $this->logger->debug("ACS received 'SAMLResponse' in POST request (base64 decoded): <pre>@message</pre>", ['@message' => $response]);
        }
        else {
          $this->logger->warning("ACS received 'SAMLResponse' in POST request which could not be base64 decoded: <pre>@message</pre>", ['@message' => $this->requestStack->getCurrentRequest()->request->get('SAMLResponse')]);
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

    try {
      $this->doLogin($unique_id, $account);
    }
    catch (UserVisibleException $e) {
      if ($config->get('login_error_keep_session')) {
        $this->saveSamlSession();
      }
      throw $e;
    }

    $this->saveSamlSession();

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
        $name = $this->getAccountUsername(reset($attributes['displayName']));
        $mail = (isset($attributes['mail'])) ? reset($attributes['mail']) : NULL;

        $account_data = [
          'mail' => $mail,
          'name' => $name,
          'type' => 'customer',
          'langcode' => 'fi',
          'preferred_langcode' => 'fi',
          'preferred_admin_langcode' => 'fi',
          'field_saml_hash' => $unique_id,
        ];

        $account = $this->externalAuth->register($unique_id, 'samlauth', $account_data);
        $this->createAuthUserRequest($account, $attributes);
        $this->externalAuth->userLoginFinalize($account, $unique_id, 'samlauth');
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
      if (empty($account->field_backend_profile->value)) {
        $attributes = $this->getAttributes();
        $this->createAuthUserRequest($account, $attributes);
      }

      // Synchronize the user account with SAML attributes if needed.
      $this->synchronizeUserAttributes($account, FALSE, $first_saml_login);
      $this->externalAuth->userLoginFinalize($account, $unique_id, 'samlauth');
    }
  }

  /**
   * Custom create auth user request function.
   */
  private function createAuthUserRequest($account, $attributes) {
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

    $store = $this->privateTempCustomer;
    $store->set('first_name', $first_name);
    $store->set('last_name', $lastname);
    $store->set('date_of_birth', $birth_day);

    $accountData = [
      'first_name' => $first_name,
      'last_name' => $lastname,
      'date_of_birth' => $birth_day,
      'national_identification_number' => $pid,
    ];

    $request = new CreateUserRequest(
      $account,
      $accountData,
      'customer'
    );

    try {
      /** @var \Drupal\asu_api\Api\BackendApi\BackendApi $backendApi */
      $response = $this->backendApi->send($request);
      $account->field_backend_profile = $response->getProfileId();
      $account->field_backend_password = $response->getPassword();
      $account->save();
    }
    catch (\Exception $e) {
      $this->logger('asu_backend_api')->emergency(
        'Exception while creating user to backend: ' . $e->getMessage()
      );
    }

    return $account;
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
    if (empty($string)) {
      return NULL;
    }

    $hash_key = getenv('ASU_HASH_KEY');

    return Crypt::hmacBase64($string, $hash_key);
  }

  /**
   * Get Drupal account user name.
   */
  private function getAccountUsername($user_name) {
    $query = $this->database->select('users_field_data', 'u');
    $query->fields('u', ['name']);
    // OR CONDITION.
    $or_group = $query->orConditionGroup();
    $or_group->condition('name', $query->escapeLike($user_name));
    $or_group->condition('name', $user_name . '_[0-9]', 'REGEXP');
    // Added OR CONDITION TO QUERY.
    $query->condition($or_group);
    $result = $query->execute()->fetchAll();
    $result_count = count($result);

    if ($result_count > 0) {
      return $user_name . '_' . $result_count + 1;
    }

    return $user_name;
  }

}
