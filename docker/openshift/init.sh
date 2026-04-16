#!/bin/bash

cd /var/www/html/public

# Write Simple OAuth keys from env vars if provided.
# In OpenShift the image filesystem is often read-only, so we write to Drupal's
# private files directory by default (configurable via DRUPAL_FILES_PRIVATE).
if [ -n "${DRUPAL_SIMPLE_OAUTH_PRIVATE_KEY_PEM}" ] || [ -n "${DRUPAL_SIMPLE_OAUTH_PUBLIC_KEY_PEM}" ]; then
  private_files_dir="${DRUPAL_FILES_PRIVATE:-sites/default/files/private}"
  key_dir="${DRUPAL_SIMPLE_OAUTH_KEY_DIR:-${private_files_dir%/}/simple_oauth}"
  private_key_path="${key_dir%/}/private.key"
  public_key_path="${key_dir%/}/public.key"
  old_umask="$(umask)"

  if ! mkdir -p "${key_dir}"; then
    echo "Container start error: Unable to create Simple OAuth key directory at '${key_dir}'."
    exit 1
  fi
  chmod 700 "${key_dir}" || true

  # Create files with correct permissions even when chmod is blocked.
  umask 077

  if [ -n "${DRUPAL_SIMPLE_OAUTH_PRIVATE_KEY_PEM}" ]; then
    if [ -f "${private_key_path}" ] && ! chmod 600 "${private_key_path}" 2>/dev/null; then
      rm -f "${private_key_path}" || true
    fi
    if [ ! -f "${private_key_path}" ]; then
      printf '%b\n' "${DRUPAL_SIMPLE_OAUTH_PRIVATE_KEY_PEM}" > "${private_key_path}"
      chmod 600 "${private_key_path}" || true
    fi
  fi

  if [ -n "${DRUPAL_SIMPLE_OAUTH_PUBLIC_KEY_PEM}" ]; then
    if [ -f "${public_key_path}" ] && ! chmod 600 "${public_key_path}" 2>/dev/null; then
      rm -f "${public_key_path}" || true
    fi
    if [ ! -f "${public_key_path}" ]; then
      printf '%b\n' "${DRUPAL_SIMPLE_OAUTH_PUBLIC_KEY_PEM}" > "${public_key_path}"
      chmod 600 "${public_key_path}" || true
    fi
  fi

  umask "${old_umask}" || true
fi

function get_deploy_id {
  if [ ! -f "sites/default/files/deploy.id" ]; then
    touch sites/default/files/deploy.id
  fi
  echo $(cat sites/default/files/deploy.id)
}

function set_deploy_id {
  echo ${1} > sites/default/files/deploy.id
}

function output_error_message {
  echo ${1}
  php ../docker/openshift/notify.php "${1}" || true
}

function deployment_in_progress {
  if [ "$(get_deploy_id)" != "$OPENSHIFT_BUILD_NAME" ]; then
    return 0
  fi

  if [ "$(drush state:get system.maintenance_mode)" = "1" ]; then
    return 0
  fi

  return 1
}

function is_drupal_module_enabled {
  if drush pm-list --status=Enabled --filter=${1} --format=json | jq --exit-status '. == []' > /dev/null; then
    return 1
  fi

  return 0
}

if [ ! -d "sites/default/files" ]; then
  output_error_message "Container start error: Public file folder does not exist. Exiting early."
  exit 1
fi

# Make sure we have active Drupal configuration.
if [ ! -f "../conf/cmi/system.site.yml" ]; then
  output_error_message "Container start error: Codebase is not deployed properly. Exiting early."
  exit 1
fi

if [ ! -n "$OPENSHIFT_BUILD_NAME" ]; then
  output_error_message "Container start error: OPENSHIFT_BUILD_NAME is not defined. Exiting early."
  exit 1
fi
