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
$config['samlauth.authentication']['idp_certs'][] = 'MIIHJzCCBQ+gAwIBAgIEBgVNqjANBgkqhkiG9w0BAQsFADB0MQswCQYDVQQGEwJG STEjMCEGA1UEChMaVmFlc3RvcmVraXN0ZXJpa2Vza3VzIFRFU1QxGDAWBgNVBAsT D1Rlc3RpdmFybWVudGVldDEmMCQGA1UEAxMdVlJLIENBIGZvciBUZXN0IFB1cnBv c2VzIC0gRzMwHhcNMjAxMDA4MTEyNjEyWhcNMjIxMDA4MjA1OTU5WjCBmzELMAkG A1UEBhMCRkkxEDAOBgNVBAgTB0ZJTkxBTkQxETAPBgNVBAcTCEhlbHNpbmtpMSQw IgYDVQQKExtEaWdpLSBqYSB2YWVzdG90aWV0b3ZpcmFzdG8xEjAQBgNVBAUTCTAy NDU0MzctMjEtMCsGA1UEAxMkc2FtbC1zaWduaW5nLXRlc3RpLmFwcm8udHVubmlz dHVzLmZpMIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAuIBsdZOhAkGq z8QfDG9LLNFACOvKI2PxxXjhkI5hLdrtYsYhcKNkLLX66ASyefpOBt0OsG74FWif x3hUlK4pt/t8LBGixBWs6A1aKsQL8McriGDjTaK3ynQP8ffJ48ECDcKWkUaGeH2f SnZ7yLjtW4MH3gtQGYJNLQ3qAqE6RstXoMqlpkx12rbNk1EgZcksxa8aY4tThACR Vsc3MVBzIVwkT6U+Lxnxy3SC8qxX4vHxPhF7C1GbzLPqeI5oRJUMsXqX/Z6xh9om 0ZPoT27XPW731mSD6eu7W0LinYxOcMWPkjqS0zLiKFn3Mxml/XSRSOnFKMJm+Dxi sriAj1OwomotKhE/PReXYg7gjQCvU8xZcOtHnsYZrOAampDgzv/ehG41GEEHbSl2 Vj8f/r2/pVK037oMW2cezDUaTX7P9yYsKANOTzM1eADLLbdnXT5i0J5ABbbRPrww RJ77My0LFPgtFjLQTMOMcy7GssjZUibS2gjta5nTEGIJ5Q/R71ArAgMBAAGjggIX MIICEzAfBgNVHSMEGDAWgBRbzoacx1ND5gK5+3FsjG2jIOWx+DAdBgNVHQ4EFgQU jLExkibI7kFQ49WG6qWT4xeKxcYwDgYDVR0PAQH/BAQDAgTwMIHXBgNVHSAEgc8w gcwwCAYGBACPegEHMIG/BgkqgXaEBWMKIgEwgbEwJwYIKwYBBQUHAgEWG2h0dHA6 Ly93d3cuZmluZWlkLmZpL2Nwczk5LzCBhQYIKwYBBQUHAgIweRp3VmFybWVubmVw b2xpdGlpa2thIG9uIHNhYXRhdmlsbGEgLSBDZXJ0aWZpa2F0IHBvbGljeSBmaW5u cyAtIENlcnRpZmljYXRlIHBvbGljeSBpcyBhdmFpbGFibGUgaHR0cDovL3d3dy5m aW5laWQuZmkvY3BzOTkwDwYDVR0TAQH/BAUwAwEBADA3BgNVHR8EMDAuMCygKqAo hiZodHRwOi8vcHJveHkuZmluZWlkLmZpL2NybC92cmt0cDNjLmNybDBuBggrBgEF BQcBAQRiMGAwMAYIKwYBBQUHMAKGJGh0dHA6Ly9wcm94eS5maW5laWQuZmkvY2Ev dnJrdHAzLmNydDAsBggrBgEFBQcwAYYgaHR0cDovL29jc3B0ZXN0LmZpbmVpZC5m aS92cmt0cDMwLQYIKwYBBQUHAQMEITAfMAgGBgQAjkYBATATBgYEAI5GAQYwCQYH BACORgEGAzANBgkqhkiG9w0BAQsFAAOCAgEAP4WX7UETslDuT7TK4qbvE9nVGTL3 OfXz9Ixn5wgnNg7LcbJmHnMAu+w4SCp0RcHZ/Y4uO+M5IN9hiacD+VI5e0OMWrpv vFEzNG3qc02yVNZcFBvHCORe3Ugx3pAQ3UfaiOhoExRJBX2tgToSkEFIwy4hqJX6 bd/LVcCpdz34Ncgp2y7j58o6KfnQzKbq+bj0iqQuwiLG2oPObHhvL8Y8KQNghit8 bXtV7RRq50Alheh/+YAv7MxutSCR5fZA6m8SzkOzFzYoEUxKmA5hKyB+dSJ3kPpC I9DWYGUzUYEy8Wi4GjSbja4cGqt3kWuqEIek7VznBZn1xfcwc6YdJ1bmKgVEdPhm pDKK16KLHga/oztKwZiAZN3yW8UI9yNjuBbq3MDWBBUyEAxMrt8EibLIcgTrBN8l RdwO3o7L2sLObbiun3kLuiQnnNNY1NS73BZ3KZmvJ7gN080mFoyXSBGFD3mihFhR oNQaYsZx3w5F8gJ1NWG+JLQbM4MRod6MKEgHV/tJvdjOg6qNokahYaROo3Fww3p3 8ifPWOtfT2BLYnQkArup8Wr7MEt+h7VWgwIOdV3VGRAkF2cdpNZQ5FMKzeBLjC73 nyOEMVQIFfRXbNevNA887Vu6g80EN9lQq0cMQGoLPTcRQXIUpD1NmjVJmRyNJK6F +Kbu8aRtFKgOGoU=';
$config['samlauth.authentication']['idp_certs'][] = 'MIIGpTCCBI2gAwIBAgIEBgVNqzANBgkqhkiG9w0BAQsFADB0MQswCQYDVQQGEwJGSTEjMCEGA1UE ChMaVmFlc3RvcmVraXN0ZXJpa2Vza3VzIFRFU1QxGDAWBgNVBAsTD1Rlc3RpdmFybWVudGVldDEm MCQGA1UEAxMdVlJLIENBIGZvciBUZXN0IFB1cnBvc2VzIC0gRzMwHhcNMjAxMDA4MTEzMzA4WhcN MjIxMDA4MjA1OTU5WjCBmTELMAkGA1UEBhMCRkkxEDAOBgNVBAgTB0ZJTkxBTkQxETAPBgNVBAcT CEhlbHNpbmtpMSQwIgYDVQQKExtEaWdpLSBqYSB2YWVzdG90aWV0b3ZpcmFzdG8xEjAQBgNVBAUT CTAyNDU0MzctMjErMCkGA1UEAxMibWV0YWRhdGEtc2lnbmluZy5hcHJvLnR1bm5pc3R1cy5maTCC ASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAM+Qu0s1HTUqN9yOHGuUTUCN91HlpN44lSwr rEIr+obPj2u3vxH9EfoydxYnCG/WPmUc78GMb59QxrY7hVZYUewYanVvYgRoMrhk1UcN6+AgjvHx WLaXY2197LGhikPB3tkuz+x81To781w2qE37GVTj+5nd2rtXV8L9AHY3xuoQSfGj8OHfrq/rtWlY dNchtZwMtMj68rdN6a8Al3S58kqsgtkC0uJNex8bLbIncoFL/JG/IaUvRBM8rVEb4pXDw/pYwtUr yDHFIJY0gaPW7xClkEEYkMa4+DTlEsfK4G+uHJsaPaT+nMFqB2wtO4YHdtDa3GnRmG1PH+SMaaM4 3/8CAwEAAaOCAhcwggITMB8GA1UdIwQYMBaAFFvOhpzHU0PmArn7cWyMbaMg5bH4MB0GA1UdDgQW BBSfK9olqbPGXeRXFJutYmtxyUUVLjAOBgNVHQ8BAf8EBAMCBPAwgdcGA1UdIASBzzCBzDAIBgYE AI96AQcwgb8GCSqBdoQFYwoiATCBsTAnBggrBgEFBQcCARYbaHR0cDovL3d3dy5maW5laWQuZmkv Y3BzOTkvMIGFBggrBgEFBQcCAjB5GndWYXJtZW5uZXBvbGl0aWlra2Egb24gc2FhdGF2aWxsYSAt IENlcnRpZmlrYXQgcG9saWN5IGZpbm5zIC0gQ2VydGlmaWNhdGUgcG9saWN5IGlzIGF2YWlsYWJs ZSBodHRwOi8vd3d3LmZpbmVpZC5maS9jcHM5OTAPBgNVHRMBAf8EBTADAQEAMDcGA1UdHwQwMC4w LKAqoCiGJmh0dHA6Ly9wcm94eS5maW5laWQuZmkvY3JsL3Zya3RwM2MuY3JsMG4GCCsGAQUFBwEB BGIwYDAwBggrBgEFBQcwAoYkaHR0cDovL3Byb3h5LmZpbmVpZC5maS9jYS92cmt0cDMuY3J0MCwG CCsGAQUFBzABhiBodHRwOi8vb2NzcHRlc3QuZmluZWlkLmZpL3Zya3RwMzAtBggrBgEFBQcBAwQh MB8wCAYGBACORgEBMBMGBgQAjkYBBjAJBgcEAI5GAQYDMA0GCSqGSIb3DQEBCwUAA4ICAQBuJpKF C7kbRyemOqfSBQNZQnoiJn5uDQWaEjeqGCt0H1NJXuVe6LNdyeQ3MFqsJlzDF1V2eRi31lXOb7h8 E20uLFF8AFKP/uqB8Pw7tcq1uuLNIwKdGfrChaOV9bUwzojmY8GdZG4nkrSoi7W/gbzwpPTWE1eg z2qrThHhHn33DZcLIUA1zg91PSw4cF/Xnz+TclN2zuLc5pG7QDs3EaPE/i1vO2ohUfVJ5Oox6O33 6pj8KBQaLbkJd07aV0vgIB1PTuACt/pVxY7rraWrEP4PRaJsTjJq1MIzuVXuNRzVBwU4sZgXONuu z+oXCIcQqK1soHlO9j6fXGj6gK4tHEf901IshZ2JmhPZhhExmmimW/50IBXm65ufAFSKlvLQwDsD DEzAcsO7BwstjVl5sUbBnGYVDAuIvdR7Oj/COQRKddfJ+1wsr0teFnI5xEPnIuvdKRuhSG8pSJH0 D++gjyz9F2ylIjst2j7q8BeDgrmSGkkFm0380Et9XFKRUTuczo/IreJ+CCIHADS8irXnSfx45H2X oItbny6S8HmwLRFtBZh6fykbBZifK5g6ec96P8FTGRWFrmYfplO0eId50LdGadBZD4FVzl2ypN6a h/vGLTk1y8+mI41Gadq1/ShXleazQyjz2Vw2V+sJsXxSAj/vPO3C9K4W7zfIFMYWYHgHFw==';

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

//if (getenv('APP_ENV') === 'testing') {
  // https://palveluhallinta.suomi.fi/fi/sivut/tunnistus/kayttoonotto/asiakastestiymparisto

  $config['samlauth.authentication']['debug_display_error_details'] = TRUE;
  $config['samlauth.authentication']['debug_log_in'] = TRUE;
  $config['samlauth.authentication']['debug_log_saml_in'] = TRUE;
  $config['samlauth.authentication']['debug_log_saml_out'] = TRUE;
  $config['samlauth.authentication']['debug_phpsaml'] = TRUE;

  // Use local IdP container. Remember to uncomment "idp" service from docker-compose.yml
  $config['samlauth.authentication']['sp_entity_id'] = 'https://nginx-asuntotuotanto-test.agw.arodevtest.hel.fi/saml/metadata/';
  $config['samlauth.authentication']['sp_private_key'] = getenv('ASU_SAML_SP');
  $config['samlauth.authentication']['sp_x509_certificate'][] = getenv('ASU_SAML_CERT');

  // Test metadata: https://tunnistus.suomi.fi/static/metadata/idp-metadata-tunnistaminen.xml
  $config['samlauth.authentication']['idp_entity_id'] = 'https://uusi.tunnistus.fi/idp1';
  $config['samlauth.authentication']['idp_single_sign_on_service'] = 'https://testi.apro.tunnistus.fi/idp/profile/SAML2/Redirect/SSO';
  $config['samlauth.authentication']['idp_single_log_out_service'] = 'https://testi.apro.tunnistus.fi/idp/profile/SAML2/Redirect/SLO';
//}
