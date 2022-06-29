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
$config['samlauth.authentication']['idp_certs'][] = getenv('ASU_SAML_CERT');

$config['samlauth.authentication']['sp_name_id_format'] = 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient';

$config['samlauth.authentication']['unique_id_attribute'] = 'nationalIdentificationNumber';

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
  $config['samlauth.authentication']['sp_entity_id'] = 'https://nginx-asuntotuotanto-test.agw.arodevtest.hel.fi/saml/metadata';
  $config['samlauth.authentication']['sp_private_key'] = 'file:/app/conf/certs/sp.key';
  $config['samlauth.authentication']['sp_x509_certificate'] = 'file:/app/conf/certs/sp.crt';

  // Test metadata: https://tunnistus.suomi.fi/static/metadata/idp-metadata-tunnistaminen.xml
  $config['samlauth.authentication']['idp_entity_id'] = 'https://uusi.tunnistus.fi/idp1';
  $config['samlauth.authentication']['idp_single_sign_on_service'] = 'https://tunnistaminen.suomi.fi/idp/profile/SAML2/Redirect/SLO';
  $config['samlauth.authentication']['idp_single_log_out_service'] = 'https://tunnistaminen.suomi.fi/idp/profile/SAML2/Redirect/SSO';
}
