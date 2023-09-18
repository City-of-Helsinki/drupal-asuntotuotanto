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

if (getenv('APP_ENV') == 'dev' || getenv('APP_ENV') == 'testing' || getenv('APP_ENV') =='development') {
  // pitää mätsätä idp:n päässä olevan arvon kanssa.
  $config['samlauth.authentication']['sp_entity_id'] = 'https://asuntotuotanto.docker.so';
  $config['samlauth.authentication']['idp_single_sign_on_service'] = 'https://nginx-asuntotuotanto-test.agw.arodevtest.hel.fi/idp/profile/SAML2/Redirect/SSO';
  $config['samlauth.authentication']['idp_single_log_out_service'] = 'https://nginx-asuntotuotanto-test.agw.arodevtest.hel.fi/idp/profile/SAML2/Redirect/SLO';
  $config['samlauth.authentication']['idp_certs'][] = 'MIIHJzCCBQ+gAwIBAgIEBgVNqjANBgkqhkiG9w0BAQsFADB0MQswCQYDVQQGEwJG STEjMCEGA1UEChMaVmFlc3RvcmVraXN0ZXJpa2Vza3VzIFRFU1QxGDAWBgNVBAsT D1Rlc3RpdmFybWVudGVldDEmMCQGA1UEAxMdVlJLIENBIGZvciBUZXN0IFB1cnBv c2VzIC0gRzMwHhcNMjAxMDA4MTEyNjEyWhcNMjIxMDA4MjA1OTU5WjCBmzELMAkG A1UEBhMCRkkxEDAOBgNVBAgTB0ZJTkxBTkQxETAPBgNVBAcTCEhlbHNpbmtpMSQw IgYDVQQKExtEaWdpLSBqYSB2YWVzdG90aWV0b3ZpcmFzdG8xEjAQBgNVBAUTCTAy NDU0MzctMjEtMCsGA1UEAxMkc2FtbC1zaWduaW5nLXRlc3RpLmFwcm8udHVubmlz dHVzLmZpMIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAuIBsdZOhAkGq z8QfDG9LLNFACOvKI2PxxXjhkI5hLdrtYsYhcKNkLLX66ASyefpOBt0OsG74FWif x3hUlK4pt/t8LBGixBWs6A1aKsQL8McriGDjTaK3ynQP8ffJ48ECDcKWkUaGeH2f SnZ7yLjtW4MH3gtQGYJNLQ3qAqE6RstXoMqlpkx12rbNk1EgZcksxa8aY4tThACR Vsc3MVBzIVwkT6U+Lxnxy3SC8qxX4vHxPhF7C1GbzLPqeI5oRJUMsXqX/Z6xh9om 0ZPoT27XPW731mSD6eu7W0LinYxOcMWPkjqS0zLiKFn3Mxml/XSRSOnFKMJm+Dxi sriAj1OwomotKhE/PReXYg7gjQCvU8xZcOtHnsYZrOAampDgzv/ehG41GEEHbSl2 Vj8f/r2/pVK037oMW2cezDUaTX7P9yYsKANOTzM1eADLLbdnXT5i0J5ABbbRPrww RJ77My0LFPgtFjLQTMOMcy7GssjZUibS2gjta5nTEGIJ5Q/R71ArAgMBAAGjggIX MIICEzAfBgNVHSMEGDAWgBRbzoacx1ND5gK5+3FsjG2jIOWx+DAdBgNVHQ4EFgQU jLExkibI7kFQ49WG6qWT4xeKxcYwDgYDVR0PAQH/BAQDAgTwMIHXBgNVHSAEgc8w gcwwCAYGBACPegEHMIG/BgkqgXaEBWMKIgEwgbEwJwYIKwYBBQUHAgEWG2h0dHA6 Ly93d3cuZmluZWlkLmZpL2Nwczk5LzCBhQYIKwYBBQUHAgIweRp3VmFybWVubmVw b2xpdGlpa2thIG9uIHNhYXRhdmlsbGEgLSBDZXJ0aWZpa2F0IHBvbGljeSBmaW5u cyAtIENlcnRpZmljYXRlIHBvbGljeSBpcyBhdmFpbGFibGUgaHR0cDovL3d3dy5m aW5laWQuZmkvY3BzOTkwDwYDVR0TAQH/BAUwAwEBADA3BgNVHR8EMDAuMCygKqAo hiZodHRwOi8vcHJveHkuZmluZWlkLmZpL2NybC92cmt0cDNjLmNybDBuBggrBgEF BQcBAQRiMGAwMAYIKwYBBQUHMAKGJGh0dHA6Ly9wcm94eS5maW5laWQuZmkvY2Ev dnJrdHAzLmNydDAsBggrBgEFBQcwAYYgaHR0cDovL29jc3B0ZXN0LmZpbmVpZC5m aS92cmt0cDMwLQYIKwYBBQUHAQMEITAfMAgGBgQAjkYBATATBgYEAI5GAQYwCQYH BACORgEGAzANBgkqhkiG9w0BAQsFAAOCAgEAP4WX7UETslDuT7TK4qbvE9nVGTL3 OfXz9Ixn5wgnNg7LcbJmHnMAu+w4SCp0RcHZ/Y4uO+M5IN9hiacD+VI5e0OMWrpv vFEzNG3qc02yVNZcFBvHCORe3Ugx3pAQ3UfaiOhoExRJBX2tgToSkEFIwy4hqJX6 bd/LVcCpdz34Ncgp2y7j58o6KfnQzKbq+bj0iqQuwiLG2oPObHhvL8Y8KQNghit8 bXtV7RRq50Alheh/+YAv7MxutSCR5fZA6m8SzkOzFzYoEUxKmA5hKyB+dSJ3kPpC I9DWYGUzUYEy8Wi4GjSbja4cGqt3kWuqEIek7VznBZn1xfcwc6YdJ1bmKgVEdPhm pDKK16KLHga/oztKwZiAZN3yW8UI9yNjuBbq3MDWBBUyEAxMrt8EibLIcgTrBN8l RdwO3o7L2sLObbiun3kLuiQnnNNY1NS73BZ3KZmvJ7gN080mFoyXSBGFD3mihFhR oNQaYsZx3w5F8gJ1NWG+JLQbM4MRod6MKEgHV/tJvdjOg6qNokahYaROo3Fww3p3 8ifPWOtfT2BLYnQkArup8Wr7MEt+h7VWgwIOdV3VGRAkF2cdpNZQ5FMKzeBLjC73 nyOEMVQIFfRXbNevNA887Vu6g80EN9lQq0cMQGoLPTcRQXIUpD1NmjVJmRyNJK6F +Kbu8aRtFKgOGoU=';
  $config['samlauth.authentication']['idp_certs'][] = 'MIIGpTCCBI2gAwIBAgIEBgVNqzANBgkqhkiG9w0BAQsFADB0MQswCQYDVQQGEwJGSTEjMCEGA1UE ChMaVmFlc3RvcmVraXN0ZXJpa2Vza3VzIFRFU1QxGDAWBgNVBAsTD1Rlc3RpdmFybWVudGVldDEm MCQGA1UEAxMdVlJLIENBIGZvciBUZXN0IFB1cnBvc2VzIC0gRzMwHhcNMjAxMDA4MTEzMzA4WhcN MjIxMDA4MjA1OTU5WjCBmTELMAkGA1UEBhMCRkkxEDAOBgNVBAgTB0ZJTkxBTkQxETAPBgNVBAcT CEhlbHNpbmtpMSQwIgYDVQQKExtEaWdpLSBqYSB2YWVzdG90aWV0b3ZpcmFzdG8xEjAQBgNVBAUT CTAyNDU0MzctMjErMCkGA1UEAxMibWV0YWRhdGEtc2lnbmluZy5hcHJvLnR1bm5pc3R1cy5maTCC ASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAM+Qu0s1HTUqN9yOHGuUTUCN91HlpN44lSwr rEIr+obPj2u3vxH9EfoydxYnCG/WPmUc78GMb59QxrY7hVZYUewYanVvYgRoMrhk1UcN6+AgjvHx WLaXY2197LGhikPB3tkuz+x81To781w2qE37GVTj+5nd2rtXV8L9AHY3xuoQSfGj8OHfrq/rtWlY dNchtZwMtMj68rdN6a8Al3S58kqsgtkC0uJNex8bLbIncoFL/JG/IaUvRBM8rVEb4pXDw/pYwtUr yDHFIJY0gaPW7xClkEEYkMa4+DTlEsfK4G+uHJsaPaT+nMFqB2wtO4YHdtDa3GnRmG1PH+SMaaM4 3/8CAwEAAaOCAhcwggITMB8GA1UdIwQYMBaAFFvOhpzHU0PmArn7cWyMbaMg5bH4MB0GA1UdDgQW BBSfK9olqbPGXeRXFJutYmtxyUUVLjAOBgNVHQ8BAf8EBAMCBPAwgdcGA1UdIASBzzCBzDAIBgYE AI96AQcwgb8GCSqBdoQFYwoiATCBsTAnBggrBgEFBQcCARYbaHR0cDovL3d3dy5maW5laWQuZmkv Y3BzOTkvMIGFBggrBgEFBQcCAjB5GndWYXJtZW5uZXBvbGl0aWlra2Egb24gc2FhdGF2aWxsYSAt IENlcnRpZmlrYXQgcG9saWN5IGZpbm5zIC0gQ2VydGlmaWNhdGUgcG9saWN5IGlzIGF2YWlsYWJs ZSBodHRwOi8vd3d3LmZpbmVpZC5maS9jcHM5OTAPBgNVHRMBAf8EBTADAQEAMDcGA1UdHwQwMC4w LKAqoCiGJmh0dHA6Ly9wcm94eS5maW5laWQuZmkvY3JsL3Zya3RwM2MuY3JsMG4GCCsGAQUFBwEB BGIwYDAwBggrBgEFBQcwAoYkaHR0cDovL3Byb3h5LmZpbmVpZC5maS9jYS92cmt0cDMuY3J0MCwG CCsGAQUFBzABhiBodHRwOi8vb2NzcHRlc3QuZmluZWlkLmZpL3Zya3RwMzAtBggrBgEFBQcBAwQh MB8wCAYGBACORgEBMBMGBgQAjkYBBjAJBgcEAI5GAQYDMA0GCSqGSIb3DQEBCwUAA4ICAQBuJpKF C7kbRyemOqfSBQNZQnoiJn5uDQWaEjeqGCt0H1NJXuVe6LNdyeQ3MFqsJlzDF1V2eRi31lXOb7h8 E20uLFF8AFKP/uqB8Pw7tcq1uuLNIwKdGfrChaOV9bUwzojmY8GdZG4nkrSoi7W/gbzwpPTWE1eg z2qrThHhHn33DZcLIUA1zg91PSw4cF/Xnz+TclN2zuLc5pG7QDs3EaPE/i1vO2ohUfVJ5Oox6O33 6pj8KBQaLbkJd07aV0vgIB1PTuACt/pVxY7rraWrEP4PRaJsTjJq1MIzuVXuNRzVBwU4sZgXONuu z+oXCIcQqK1soHlO9j6fXGj6gK4tHEf901IshZ2JmhPZhhExmmimW/50IBXm65ufAFSKlvLQwDsD DEzAcsO7BwstjVl5sUbBnGYVDAuIvdR7Oj/COQRKddfJ+1wsr0teFnI5xEPnIuvdKRuhSG8pSJH0 D++gjyz9F2ylIjst2j7q8BeDgrmSGkkFm0380Et9XFKRUTuczo/IreJ+CCIHADS8irXnSfx45H2X oItbny6S8HmwLRFtBZh6fykbBZifK5g6ec96P8FTGRWFrmYfplO0eId50LdGadBZD4FVzl2ypN6a h/vGLTk1y8+mI41Gadq1/ShXleazQyjz2Vw2V+sJsXxSAj/vPO3C9K4W7zfIFMYWYHgHFw==';
  // Use local IdP container. Remember to uncomment "idp" service from docker-compose.yml
  $config['samlauth.authentication']['sp_entity_id'] = 'https://nginx-asuntotuotanto-test.agw.arodevtest.hel.fi/saml/metadata/';
  // Metadata: https://tunnistus.suomi.fi/static/metadata/idp-metadata-tunnistaminen.xml
  // Test metadata https://testi.apro.tunnistus.fi/static/metadata/idp-metadata.xml
  $config['samlauth.authentication']['idp_entity_id'] = 'https://testi.apro.tunnistus.fi/idp1';
  $config['samlauth.authentication']['idp_single_sign_on_service'] = 'https://testi.apro.tunnistus.fi/idp/profile/SAML2/Redirect/SSO';
  $config['samlauth.authentication']['idp_single_log_out_service'] = 'https://testi.apro.tunnistus.fi/idp/profile/SAML2/Redirect/SLO';
}
if (getenv('APP_ENV') == 'prod' || getenv('APP_ENV') =='stg') {
  // pitää mätsätä idp:n päässä olevan arvon kanssa.
  $config['samlauth.authentication']['idp_single_sign_on_service'] = 'https://asuntotuotanto.hel.fi/idp/profile/SAML2/Redirect/SSO';
  $config['samlauth.authentication']['idp_single_log_out_service'] = 'https://asuntotuotanto.hel.fi/idp/profile/SAML2/Redirect/SLO';
  $config['samlauth.authentication']['idp_certs'][] = 'MIIHETCCBPmgAwIBAgIPAYOc+chgjIBtRjO+mf8BMA0GCSqGSIb3DQEBDQUAMHsx CzAJBgNVBAYTAkZJMScwJQYDVQQKDB5EaWdpLSBqYSB2YWVzdG90aWV0b3ZpcmFz dG8gQ0ExGjAYBgNVBAsMEVBhbHZlbHV2YXJtZW50ZWV0MScwJQYDVQQDDB5EVlYg U2VydmljZSBDZXJ0aWZpY2F0ZXMgLSBHNVIwHhcNMjIxMDA5MjEwMDAwWhcNMjQx MDA5MjA1OTU5WjCBoTELMAkGA1UEBhMCRkkxEDAOBgNVBAgMB0ZJTkxBTkQxETAP BgNVBAcMCEhlbHNpbmtpMSQwIgYDVQQKDBtEaWdpLSBqYSB2YWVzdG90aWV0b3Zp cmFzdG8xEjAQBgNVBAUTCTAyNDU0MzctMjEzMDEGA1UEAwwqc2FtbC1zaWduaW5n LmlkcC50dW5uaXN0YXV0dW1pbmVuLnN1b21pLmZpMIIBojANBgkqhkiG9w0BAQEF AAOCAY8AMIIBigKCAYEAyVECu95O/7TiUG3xitMLIq8/JJ5hUpxis64yRhGJtXip 3VYiJ30AADZsAuU2WBc3jv8Dlsg3y+8/uVbhpclCHVi9vEedQwwsl0pfnNtGQy24 ikpCioKsu1LWoSocqr5IGJGEzFtFRygRTA4MuI3OFcZw+71nfWVP3g4nvnLkMw0h DHW+t6vY3lp1yYMlsTKnCKVB8TMICRq3F/IGHxyNNeLBjeWNLDV7P2VJ36oaw9ys RPYV0hi/At0vn4kPRMk+9qupfzGb2HD7mTGYtTN9wZa3881pIXjwJFFYEvcNcaCK 7qZYfUDyVMhdGhbcznYe8Lf6eNC9C8kLTsIuUYU3nvADKXhuTjrgdiCW3wiX0WWn sG+v+xpXjSe/yXGP7+dBhJoGdR5D2XX1etJvsJ0kvGiEqRh0A43xyB0YPEtFVTK0 SiW7X4i6u5kVj2Ask/N913M0WJbmrjRVa72kZFakAi8vJ97TMEMpjPZnK1eM5hXJ e6O9vUZTmdjOv2WZ7bjjAgMBAAGjggHpMIIB5TAfBgNVHSMEGDAWgBTkkLCoF4Oj nprV/T3JWWsfILLivjAdBgNVHQ4EFgQUrWHpd43mS357O5+3S130+2soTdIwDgYD VR0PAQH/BAQDAgbAMIHXBgNVHSAEgc8wgcwwCAYGBACPegEHMIG/BgoqgXaEBQEK gjECMIGwMCcGCCsGAQUFBwIBFhtodHRwOi8vd3d3LmZpbmVpZC5maS9jcHM1NC8w gYQGCCsGAQUFBwICMHgadlZhcm1lbm5lcG9saXRpaWtrYSBvbiBzYWF0YXZpbGxh IC0gQ2VydGlmaWthdHBvbGljeSBmaW5ucyAtIENlcnRpZmljYXRlIHBvbGljeSBp cyBhdmFpbGFibGUgaHR0cDovL3d3dy5maW5laWQuZmkvY3BzNTQwDwYDVR0TAQH/ BAUwAwEBADA4BgNVHR8EMTAvMC2gK6AphidodHRwOi8vcHJveHkuZmluZWlkLmZp L2NybC9kdnZzcDVyYy5jcmwwbgYIKwYBBQUHAQEEYjBgMDIGCCsGAQUFBzAChiZo dHRwOi8vcHJveHkuZmluZWlkLmZpL2NhL2R2dnNwNXJjLmNydDAqBggrBgEFBQcw AYYeaHR0cDovL29jc3AuZmluZWlkLmZpL2R2dnNwNXJjMA0GCSqGSIb3DQEBDQUA A4ICAQCR0p59u7EFXrECZ9tXCQiNhAd6GDPezr6mTaQ2eJbAb8xTjrUP3IaZXcpB JHZWw3gRXh9mruntAhKbztKfw+3qpnLVD/owwpPnce/yDMQXTDpsyst3tAgZemKj yePS8vTQlCOYlb4ObbYrhnatgqPAP38DjebPmk4/6f2EIj0Qbu3a/oseT5yS0gny ewVYqCnbx96RAicG0ryZSQkmpSle6r0/qFbESzchzDDYOMrUT4IdzSHMJeKuhVQd eJ7QTViN81HckpJd2e7N8a6Arjes73wMXNHCXR2T91B/RIANpfNDeAtz9r9mhIUd xGVdQDmIp4yFo1aaXcJnm6JaFaXeENe46TMxUm3+zx4fmy8AwUA6GjXmiv8ljTHZ lBGohzu0L9LF292HM8lF+dXHAOLlYWEst7b7Rb5GZ13Qk+JlO/hDvh3/PA0aXPey R1f5sqYPcR/tmSKKxNI9OQPjzKC3giZ5KmK03w4HOf1/VpWtp20/dzaXlknUWpCO OC/fvHdevq0Ed4BrVPedlVT/yZeX5nVwjX53zm3mG5LV36+t0wr2fSL+dureHDd2 TAxuyWMN/9x+UcADgCAOMouDH2CFTz2t7LXR31htY5mwSuj88JpisDmObe3O57hs bok4mZYV50OT08sJjsiVV4QMmR4BaVvVrQKp0yIbuSEM0vZplw==';
  $config['samlauth.authentication']['idp_certs'][] = 'MIIHDDCCBPSgAwIBAgISBwAAAXWYOQnIymkhNi5HHVGvMA0GCSqGSIb3DQEBCwUA MHgxCzAJBgNVBAYTAkZJMSEwHwYDVQQKExhWYWVzdG9yZWtpc3RlcmlrZXNrdXMg Q0ExGjAYBgNVBAsTEVBhbHZlbHV2YXJtZW50ZWV0MSowKAYDVQQDEyFWUksgQ0Eg Zm9yIFNlcnZpY2UgUHJvdmlkZXJzIC0gRzMwHhcNMjAxMTA1MTIwMDAwWhcNMjIx MTA1MjE1OTU5WjCBoTELMAkGA1UEBhMCRkkxEDAOBgNVBAgMB0ZJTkxBTkQxETAP BgNVBAcMCEhlbHNpbmtpMSQwIgYDVQQKDBtEaWdpLSBqYSB2YWVzdG90aWV0b3Zp cmFzdG8xEjAQBgNVBAUTCTAyNDU0MzctMjEzMDEGA1UEAwwqc2FtbC1zaWduaW5n LmlkcC50dW5uaXN0YXV0dW1pbmVuLnN1b21pLmZpMIIBojANBgkqhkiG9w0BAQEF AAOCAY8AMIIBigKCAYEAyVECu95O/7TiUG3xitMLIq8/JJ5hUpxis64yRhGJtXip 3VYiJ30AADZsAuU2WBc3jv8Dlsg3y+8/uVbhpclCHVi9vEedQwwsl0pfnNtGQy24 ikpCioKsu1LWoSocqr5IGJGEzFtFRygRTA4MuI3OFcZw+71nfWVP3g4nvnLkMw0h DHW+t6vY3lp1yYMlsTKnCKVB8TMICRq3F/IGHxyNNeLBjeWNLDV7P2VJ36oaw9ys RPYV0hi/At0vn4kPRMk+9qupfzGb2HD7mTGYtTN9wZa3881pIXjwJFFYEvcNcaCK 7qZYfUDyVMhdGhbcznYe8Lf6eNC9C8kLTsIuUYU3nvADKXhuTjrgdiCW3wiX0WWn sG+v+xpXjSe/yXGP7+dBhJoGdR5D2XX1etJvsJ0kvGiEqRh0A43xyB0YPEtFVTK0 SiW7X4i6u5kVj2Ask/N913M0WJbmrjRVa72kZFakAi8vJ97TMEMpjPZnK1eM5hXJ e6O9vUZTmdjOv2WZ7bjjAgMBAAGjggHkMIIB4DAfBgNVHSMEGDAWgBRlBOgtkufL KqtXFahlKqr6txZ09jAdBgNVHQ4EFgQUrWHpd43mS357O5+3S130+2soTdIwDgYD VR0PAQH/BAQDAgbAMIHXBgNVHSAEgc8wgcwwCAYGBACPegEHMIG/BgkqgXaEBQEK IgEwgbEwJwYIKwYBBQUHAgEWG2h0dHA6Ly93d3cuZmluZWlkLmZpL2NwczMzLzCB hQYIKwYBBQUHAgIweRp3VmFybWVubmVwb2xpdGlpa2thIG9uIHNhYXRhdmlsbGEg LSBDZXJ0aWZpa2F0IHBvbGljeSBmaW5ucyAtIENlcnRpZmljYXRlIHBvbGljeSBp cyBhdmFpbGFibGUgaHR0cDovL3d3dy5maW5laWQuZmkvY3BzMzMwDwYDVR0TAQH/ BAUwAwEBADA3BgNVHR8EMDAuMCygKqAohiZodHRwOi8vcHJveHkuZmluZWlkLmZp L2NybC92cmtzcDNjLmNybDBqBggrBgEFBQcBAQReMFwwMAYIKwYBBQUHMAKGJGh0 dHA6Ly9wcm94eS5maW5laWQuZmkvY2EvdnJrc3AzLmNydDAoBggrBgEFBQcwAYYc aHR0cDovL29jc3AuZmluZWlkLmZpL3Zya3NwMzANBgkqhkiG9w0BAQsFAAOCAgEA g/mPpksTTULqJijY4QGnPy+GmOQs2sWQIM4lRX2cmzYTZifktvnjdKrjxEu4ppVw Punhc4lMCgj1fqS5vSa5fPf7PKaAE7Znr9FepMvhKuUUCW2ftpbQ5ce0qRep4pB7 FRgW7O2rGdpx2zQjLHWo6r/8i8nhgLW61SIC3ebscRm9TLOf/i0jfXMJaNphKrZf pXCaXMTKF/DCtyavzCW+HuDI/L+tA/uoKCgY5W95EMrAQPC8cxdCDUUDTyIx0W2L 9C9dMB9Z/8Y75tpLSx7e6DG1Acd9za1Uw1lVOzzGUDisIQWwhPrMepPEGEbBjJCX M6lxovJHyWzcvuub34KScp8jmq/O33mOuNOSSWkwxtd2dnKFhahH/Gsx/2YbMcSK vEb3dGt7oxgFYnT93g9aGaQJEJxeWyQm3tHY3gRWb8XjQjZNJJVP7aOz/Uxarl/P hgtTQyjTw2q0KxEnMO5yS4apau8remEjJHJt9pIA4tpnsSeQzkt3XNbiieKYg9yM PQ4V4N9a5FP/loja6d2YNEgM9fvvxZiArZ5JGEPdlmy9oe9T5FboTV3GE9fxcTmW zrivoa2nMf7Hz5jOaYuxIH3ttDfMEk5+skxtwwkeuZ+cnkMXbhAWU/3JQga0piui fXAieg1MJEbkcbMbV6om6EhHv15ZBfdO4b4fo+eFK4M=';
  // Metadata: https://tunnistus.suomi.fi/static/metadata/idp-metadata-tunnistaminen.xml
  // Test metadata https://testi.apro.tunnistus.fi/static/metadata/idp-metadata.xml
  $config['samlauth.authentication']['idp_entity_id'] = 'https://tunnistautuminen.suomi.fi/idp1';
  $config['samlauth.authentication']['idp_single_sign_on_service'] = 'https://tunnistautuminen.suomi.fi/idp/profile/SAML2/Redirect/SSO';
  $config['samlauth.authentication']['idp_single_log_out_service'] = 'https://tunnistautuminen.suomi.fi/idp/profile/SAML2/POST/SLO';
}
if (getenv('APP_ENV') =='stg') {
  // pitää mätsätä idp:n päässä olevan arvon kanssa.
  $config['samlauth.authentication']['sp_entity_id'] = 'drupal-asuntotuotanto.stage.hel.ninja';
}
if (getenv('APP_ENV') =='prod') {
  // pitää mätsätä idp:n päässä olevan arvon kanssa.
  $config['samlauth.authentication']['sp_entity_id'] = 'https://asuntotuotanto.hel.fi';
}
