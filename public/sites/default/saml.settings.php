<?php
$config['samlauth.authentication']['strict'] = FALSE;
$config['samlauth.authentication']['sp_name_id_format'] = 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient';
$config['samlauth.authentication']['unique_id_attribute'] = 'nationalIdentificationNumber';

$config['samlauth.authentication']['security_authn_requests_sign'] = TRUE;
$config['samlauth.authentication']['security_logout_requests_sign'] = FALSE;
$config['samlauth.authentication']['security_logout_responses_sign'] = FALSE;
$config['samlauth.authentication']['security_metadata_sign'] = TRUE;
$config['samlauth.authentication']['security_messages_sign'] = FALSE;
$config['samlauth.authentication']['security_assertions_encrypt'] = FALSE;
$config['samlauth.authentication']['security_assertions_signed'] = TRUE;
$config['samlauth.authentication']['security_want_name_id'] = FALSE;
$config['samlauth.authentication']['security_nameid_encrypted'] = FALSE;
$config['samlauth.authentication']['security_request_authn_context'] = FALSE;
$config['samlauth.authentication']['security_allow_repeat_attribute_name'] = FALSE;
$config['samlauth.authentication']['security_lowercase_url_encoding'] = FALSE;
$config['samlauth.authentication']['security_signature_algorithm'] = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
$config['samlauth.authentication']['security_encryption_algorithm'] = 'http://www.w3.org/2001/04/xmlenc#aes256-cbc';

// https://palveluhallinta.suomi.fi/fi/sivut/tunnistus/kayttoonotto/asiakastestiymparisto

$config['samlauth.authentication']['debug_display_error_details'] = FALSE;
$config['samlauth.authentication']['debug_log_in'] = FALSE;
$config['samlauth.authentication']['debug_log_saml_in'] = FALSE;
$config['samlauth.authentication']['debug_log_saml_out'] = FALSE;
$config['samlauth.authentication']['debug_phpsaml'] = FALSE;

// redirects fail.
$config['samlauth.authentication']['login_redirect_url'] = '/auth/return';
$config['samlauth.authentication']['error_redirect_url'] = '/user/login';
$config['samlauth.authentication']['error_throw'] = FALSE;
$config['samlauth.authentication']['logout_different_user'] = FALSE;
$config['samlauth.authentication']['create_users'] = TRUE;
$config['samlauth.authentication']['user_name_attribute'] = 'displayName';
$config['samlauth.authentication']['user_mail_attribute'] = 'mail';

$config['samlauth.authentication']['sp_private_key'] = getenv('ASU_SAML_SP');
$config['samlauth.authentication']['sp_x509_certificate'] = getenv('ASU_SAML_CERT');
$config['samlauth.authentication']['sp_entity_id'] = getenv('ASU_SP_ENTITY_ID');
// Metadata: https://tunnistus.suomi.fi/static/metadata/idp-metadata-tunnistaminen.xml
// Test metadata https://static.apro.tunnistus.fi/static/metadata/idp-metadata.xml
$config['samlauth.authentication']['idp_certs'][] = getenv('ASU_IPD_CERT');
$config['samlauth.authentication']['idp_certs'][] = getenv('ASU_IPD_CERT_2');

if (getenv('APP_ENV') == 'dev' || getenv('APP_ENV') == 'testing' || getenv('APP_ENV') =='development') {
  // pitää mätsätä idp:n päässä olevan arvon kanssa.
  $config['samlauth.authentication']['sp_entity_id'] = 'https://asuntotuotanto.docker.so';
  $config['samlauth.authentication']['idp_single_sign_on_service'] = 'https://nginx-asuntotuotanto-test.agw.arodevtest.hel.fi/idp/profile/SAML2/Redirect/SSO';
  $config['samlauth.authentication']['idp_single_log_out_service'] = 'https://nginx-asuntotuotanto-test.agw.arodevtest.hel.fi/idp/profile/SAML2/Redirect/SLO';
  // Use local IdP container. Remember to uncomment "idp" service from docker-compose.yml
  $config['samlauth.authentication']['sp_entity_id'] = 'https://nginx-asuntotuotanto-test.agw.arodevtest.hel.fi/saml/metadata/';
  $config['samlauth.authentication']['idp_entity_id'] = 'https://testi.apro.tunnistus.fi/idp1';
  $config['samlauth.authentication']['idp_single_sign_on_service'] = 'https://testi.apro.tunnistus.fi/idp/profile/SAML2/Redirect/SSO';
  $config['samlauth.authentication']['idp_single_log_out_service'] = 'https://testi.apro.tunnistus.fi/idp/profile/SAML2/Redirect/SLO';
}
if (getenv('APP_ENV') == 'prod' || getenv('APP_ENV') =='stg') {
  // pitää mätsätä idp:n päässä olevan arvon kanssa.
  $config['samlauth.authentication']['idp_single_sign_on_service'] = 'https://asuntotuotanto.hel.fi/idp/profile/SAML2/Redirect/SSO';
  $config['samlauth.authentication']['idp_single_log_out_service'] = 'https://asuntotuotanto.hel.fi/idp/profile/SAML2/Redirect/SLO';
  $config['samlauth.authentication']['idp_entity_id'] = 'https://tunnistautuminen.suomi.fi/idp1';
  $config['samlauth.authentication']['idp_single_sign_on_service'] = 'https://tunnistautuminen.suomi.fi/idp/profile/SAML2/Redirect/SSO';
  $config['samlauth.authentication']['idp_single_log_out_service'] = 'https://tunnistautuminen.suomi.fi/idp/profile/SAML2/POST/SLO';
}
