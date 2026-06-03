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

  # Normalize a PEM value that may arrive as a single line (e.g. from Azure
  # Key Vault) or with escaped newlines, and write it to the given path with
  # proper 64-char wrapping required by OpenSSL.
  write_pem_key() {
    local raw="$1"
    local path="$2"
    # Convert literal '\n' / '\r' escape sequences to real newlines.
    local normalized
    normalized=$(printf '%b' "${raw}" | tr -d '\r')
    # If the normalized value already contains real newlines inside, write
    # as-is; otherwise, rewrap the base64 body to 64 char lines.
    if printf '%s' "${normalized}" | awk 'END { exit (NR > 1) ? 0 : 1 }'; then
      printf '%s\n' "${normalized}" > "${path}"
      return 0
    fi
    local label
    label=$(printf '%s' "${normalized}" | sed -n 's/.*-----BEGIN \([A-Z0-9 ]*\)-----.*/\1/p' | head -n 1)
    if [ -z "${label}" ]; then
      # Unknown format; write as-is and let OpenSSL error surface.
      printf '%s\n' "${normalized}" > "${path}"
      return 0
    fi
    local body
    body=$(printf '%s' "${normalized}" \
      | sed -E "s/-----BEGIN ${label}-----//; s/-----END ${label}-----//" \
      | tr -d '[:space:]')
    {
      printf -- '-----BEGIN %s-----\n' "${label}"
      printf '%s' "${body}" | fold -w 64
      printf '\n-----END %s-----\n' "${label}"
    } > "${path}"
  }

  # Always rewrite the key files when the env vars are set so that corrupt
  # contents from previous deploys are replaced. rm+create ensures a fresh
  # inode with the current umask.
  if [ -n "${DRUPAL_SIMPLE_OAUTH_PRIVATE_KEY_PEM}" ]; then
    rm -f "${private_key_path}" 2>/dev/null || true
    write_pem_key "${DRUPAL_SIMPLE_OAUTH_PRIVATE_KEY_PEM}" "${private_key_path}"
    chmod 600 "${private_key_path}" 2>/dev/null || true
  fi

  if [ -n "${DRUPAL_SIMPLE_OAUTH_PUBLIC_KEY_PEM}" ]; then
    rm -f "${public_key_path}" 2>/dev/null || true
    write_pem_key "${DRUPAL_SIMPLE_OAUTH_PUBLIC_KEY_PEM}" "${public_key_path}"
    chmod 644 "${public_key_path}" 2>/dev/null || true
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
