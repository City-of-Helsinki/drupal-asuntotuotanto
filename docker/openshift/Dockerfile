ARG DRUPAL_DOCKER_TAG=8.3
FROM ghcr.io/city-of-helsinki/drupal-docker-base:${DRUPAL_DOCKER_TAG}

COPY / /var/www/html/
WORKDIR /var/www/html
RUN composer install --no-progress --profile --prefer-dist --no-interaction --no-dev --optimize-autoloader

# Copy ALL deploy scripts
COPY docker/openshift/entrypoints/ /entrypoints
RUN chmod +x /entrypoints/*

COPY docker/openshift/init.sh /

# Copy cron scripts
RUN mkdir /crons
COPY docker/openshift/crons/ /crons
COPY docker/openshift/cron-entrypoint.sh /usr/local/bin/cron-entrypoint
RUN chmod +x /crons/* /usr/local/bin/cron-entrypoint

# Copy nginx overrides.
COPY docker/openshift/custom.locations /etc/nginx/conf.d/custom.locations

# Project-specific ini settings
COPY docker/openshift/php-ini-overrides.ini /etc/php83/conf.d/
