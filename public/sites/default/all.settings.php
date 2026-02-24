<?php

/**
 * @file
 * Contains site specific overrides.
 */

// Allow Symfony Mailer Lite sendmail command used by transport config.
$settings['mailer_sendmail_commands'][] = '/usr/sbin/sendmail -bs';
