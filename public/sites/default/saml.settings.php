<?php
// Test metadata: https://login-test.it.helsinki.fi/metadata/sign-hy-test-metadata.xml

$config['samlauth.authentication']['strict'] = FALSE;

// pitää mätsätä idp:n päässä olevan arvon kanssa.
//$config['samlauth.authentication']['sp_entity_id'] = 'asuntotuotanto';
$config['samlauth.authentication']['sp_entity_id'] = 'https://asuntotuotanto.docker.so';

// $config['samlauth.authentication']['sp_private_key'] = 'file:/app/conf/certs/sp1.test.helsinki.fi-shib.key';
// $config['samlauth.authentication']['sp_x509_certificate'] = 'file:/app/conf/certs/sp1.test.helsinki.fi-shib.crt';

$config['samlauth.authentication']['idp_single_sign_on_service'] = 'https://nginx-asuntotuotanto-test.agw.arodevtest.hel.fi/idp/profile/SAML2/Redirect/SSO';
$config['samlauth.authentication']['idp_single_log_out_service'] = 'https://nginx-asuntotuotanto-test.agw.arodevtest.hel.fi/idp/profile/SAML2/Redirect/SLO';
$config['samlauth.authentication']['idp_certs'][] = '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';

$config['samlauth.authentication']['sp_name_id_format'] = 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient';

// tää on joku paluuarvo
$config['samlauth.authentication']['unique_id_attribute'] = 'uid';

$config['samlauth.authentication']['security_authn_requests_sign'] = FALSE;
$config['samlauth.authentication']['security_logout_requests_sign'] = FALSE;
$config['samlauth.authentication']['security_logout_responses_sign'] = FALSE;
$config['samlauth.authentication']['security_metadata_sign'] = FALSE;
$config['samlauth.authentication']['security_messages_sign'] = FALSE;
$config['samlauth.authentication']['security_assertions_encrypt'] = FALSE;
$config['samlauth.authentication']['security_assertions_signed'] = TRUE;
$config['samlauth.authentication']['security_want_name_id'] = FALSE;
$config['samlauth.authentication']['security_nameid_encrypted'] = FALSE;
$config['samlauth.authentication']['security_request_authn_context'] = TRUE;
$config['samlauth.authentication']['security_allow_repeat_attribute_name'] = FALSE;
$config['samlauth.authentication']['security_lowercase_url_encoding'] = FALSE;
$config['samlauth.authentication']['security_signature_algorithm'] = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
$config['samlauth.authentication']['security_encryption_algorithm'] = 'http://www.w3.org/2001/04/xmlenc#aes256-cbc';

if (getenv('APP_ENV') === 'dev') {
  $config['samlauth.authentication']['debug_display_error_details'] = TRUE;
  $config['samlauth.authentication']['debug_log_in'] = TRUE;
  $config['samlauth.authentication']['debug_log_saml_in'] = TRUE;
  $config['samlauth.authentication']['debug_log_saml_out'] = TRUE;
  $config['samlauth.authentication']['debug_phpsaml'] = TRUE;

  // Use local IdP container. Remember to uncomment "idp" service from docker-compose.yml
  $config['samlauth.authentication']['sp_entity_id'] = 'https://asuntotuotanto.docker.so/saml/metadata';
  $config['samlauth.authentication']['sp_private_key'] = 'file:/app/conf/certs/sp.key';
  $config['samlauth.authentication']['sp_x509_certificate'] = 'file:/app/conf/certs/sp.crt';

  $config['samlauth.authentication']['idp_entity_id'] = 'https://idp-asuntotuotanto.docker.so/simplesaml/shib13/idp/metadata.php';
  $config['samlauth.authentication']['idp_single_sign_on_service'] = 'https://idp-asuntotuotanto.docker.so/simplesaml/saml2/idp/SSOService.php';
  $config['samlauth.authentication']['idp_single_log_out_service'] = 'https://idp-asuntotuotanto.docker.so/simplesaml/saml2/idp/SingleLogoutService.php';
  $config['samlauth.authentication']['idp_certs'][] = 'MIIDXTCCAkWgAwIBAgIJALmVVuDWu4NYMA0GCSqGSIb3DQEBCwUAMEUxCzAJBgNVBAYTAkFVMRMwEQYDVQQIDApTb21lLVN0YXRlMSEwHwYDVQQKDBhJbnRlcm5ldCBXaWRnaXRzIFB0eSBMdGQwHhcNMTYxMjMxMTQzNDQ3WhcNNDgwNjI1MTQzNDQ3WjBFMQswCQYDVQQGEwJBVTETMBEGA1UECAwKU29tZS1TdGF0ZTEhMB8GA1UECgwYSW50ZXJuZXQgV2lkZ2l0cyBQdHkgTHRkMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzUCFozgNb1h1M0jzNRSCjhOBnR+uVbVpaWfXYIR+AhWDdEe5ryY+CgavOg8bfLybyzFdehlYdDRgkedEB/GjG8aJw06l0qF4jDOAw0kEygWCu2mcH7XOxRt+YAH3TVHa/Hu1W3WjzkobqqqLQ8gkKWWM27fOgAZ6GieaJBN6VBSMMcPey3HWLBmc+TYJmv1dbaO2jHhKh8pfKw0W12VM8P1PIO8gv4Phu/uuJYieBWKixBEyy0lHjyixYFCR12xdh4CA47q958ZRGnnDUGFVE1QhgRacJCOZ9bd5t9mr8KLaVBYTCJo5ERE8jymab5dPqe5qKfJsCZiqWglbjUo9twIDAQABo1AwTjAdBgNVHQ4EFgQUxpuwcs/CYQOyui+r1G+3KxBNhxkwHwYDVR0jBBgwFoAUxpuwcs/CYQOyui+r1G+3KxBNhxkwDAYDVR0TBAUwAwEB/zANBgkqhkiG9w0BAQsFAAOCAQEAAiWUKs/2x/viNCKi3Y6blEuCtAGhzOOZ9EjrvJ8+COH3Rag3tVBWrcBZ3/uhhPq5gy9lqw4OkvEws99/5jFsX1FJ6MKBgqfuy7yh5s1YfM0ANHYczMmYpZeAcQf2CGAaVfwTTfSlzNLsF2lW/ly7yapFzlYSJLGoVE+OHEu8g5SlNACUEfkXw+5Eghh+KzlIN7R6Q7r2ixWNFBC/jWf7NKUfJyX8qIG5md1YUeT6GBW9Bm2/1/RiO24JTaYlfLdKK9TYb8sG5B+OLab2DImG99CJ25RkAcSobWNF5zD0O6lgOo3cEdB/ksCq3hmtlC/DlLZ/D8CJ+7VuZnS1rR2naQ==';
}
