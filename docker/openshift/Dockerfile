ARG DRUPAL_DOCKER_TAG=8.1
FROM ghcr.io/city-of-helsinki/drupal-docker-base:${DRUPAL_DOCKER_TAG}

# Override PHP's maximum upload file size and post max size
RUN sed -i -E \
    's/^(upload_max_filesize|post_max_size)=.*/\1=64M/' \
    /etc/php81/conf.d/php-overrides.ini

COPY / /var/www/html/
WORKDIR /var/www/html
RUN composer install --no-progress --profile --prefer-dist --no-interaction --no-dev --optimize-autoloader

# Copy deploy script
COPY docker/openshift/entrypoints/20-deploy.sh /entrypoints
RUN chmod +x /entrypoints/20-deploy.sh

# Copy cron scripts
RUN mkdir /crons
COPY docker/openshift/crons/ /crons
RUN chmod +x /crons/*

# Copy nginx overrides.
COPY docker/openshift/custom.locations /etc/nginx/conf.d/custom.locations
