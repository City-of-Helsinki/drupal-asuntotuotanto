#!/usr/bin/env bash
#
# Resync Drupal field_backend_profile and field_backend_password from Django.
#
# For each customer/salesperson with backend fields, finds the matching Django
# profile, resets its API password, and stores new masked credentials in Drupal.
#
# Usage:
#   make sync-django-backend-credentials
#   DRUPAL_SYNC_UID=85 make sync-django-backend-credentials
#   DRY_RUN=1 make sync-django-backend-credentials
#   SYNC_LIMIT=10 make sync-django-backend-credentials
#
set -euo pipefail

DRUPAL_CONTAINER="${DRUPAL_CONTAINER:-asuntotuotanto-app}"
DJANGO_CONTAINER="${DJANGO_CONTAINER:-apartment-application-backend}"
DRUPAL_SYNC_UID="${DRUPAL_SYNC_UID:-}"
DRY_RUN="${DRY_RUN:-0}"
SYNC_LIMIT="${SYNC_LIMIT:-0}"

if ! docker ps --format '{{.Names}}' | grep -qx "${DRUPAL_CONTAINER}"; then
  echo "Drupal container not running: ${DRUPAL_CONTAINER}" >&2
  exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -qx "${DJANGO_CONTAINER}"; then
  echo "Django container not running: ${DJANGO_CONTAINER}" >&2
  exit 1
fi

echo "Syncing Django backend credentials into Drupal..."
echo "  Drupal: ${DRUPAL_CONTAINER}"
echo "  Django: ${DJANGO_CONTAINER}"
if [[ -n "${DRUPAL_SYNC_UID}" ]]; then
  echo "  Filter Drupal UID: ${DRUPAL_SYNC_UID}"
fi
if [[ "${SYNC_LIMIT}" != "0" ]]; then
  echo "  Limit: ${SYNC_LIMIT}"
fi
if [[ "${DRY_RUN}" == "1" ]]; then
  echo "  Mode: dry run (no writes)"
fi

export DRUPAL_CONTAINER DJANGO_CONTAINER DRUPAL_SYNC_UID DRY_RUN SYNC_LIMIT

python3 <<'PY'
import json
import os
import subprocess
import sys

drupal = os.environ["DRUPAL_CONTAINER"]
django = os.environ["DJANGO_CONTAINER"]
drupal_sync_uid = os.environ.get("DRUPAL_SYNC_UID", "")
dry_run = os.environ.get("DRY_RUN", "0") == "1"
sync_limit = int(os.environ.get("SYNC_LIMIT", "0") or "0")

uid_condition = ""
if drupal_sync_uid:
    uid_condition = f"$query->condition('uid', (int) '{drupal_sync_uid}');"

php = f"""
$storage = \\Drupal::entityTypeManager()->getStorage('user');
$query = $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('status', 1);
{uid_condition}
$uids = $query->execute();
$rows = [];
foreach ($uids as $uid) {{
  $account = $storage->load($uid);
  if (!$account) {{
    continue;
  }}
  if (!$account->hasRole('customer') && !$account->hasRole('salesperson')) {{
    continue;
  }}
  if (!$account->hasField('field_backend_profile')) {{
    continue;
  }}
  $rows[] = [
    'uid' => (int) $account->id(),
    'uuid' => $account->uuid(),
    'email' => $account->getEmail() ?? '',
    'has_profile' => (bool) $account->get('field_backend_profile')->value,
  ];
}}
echo json_encode(array_values($rows));
"""

result = subprocess.run(
    ["docker", "exec", drupal, "drush", "php:eval", php],
    capture_output=True,
    text=True,
    check=True,
)
users = json.loads(result.stdout.strip() or "[]")

if not users:
    print("No matching Drupal users found.")
    sys.exit(0)

processed = ok = skipped = failed = 0

for row in users:
    if sync_limit and processed >= sync_limit:
        break
    processed += 1

    uid = row["uid"]
    uuid = row["uuid"]
    email = row["email"]

    if not row["has_profile"]:
        print(f"[skip] uid={uid} ({email}): no field_backend_profile yet")
        skipped += 1
        continue

    cmd = [
        "docker",
        "exec",
        django,
        "python",
        "manage.py",
        "sync_drupal_backend_credentials",
        f"--drupal-uuid={uuid}",
        f"--email={email}",
    ]
    if dry_run:
        cmd.append("--dry-run")

    proc = subprocess.run(cmd, capture_output=True, text=True)
    if proc.returncode != 0:
        err = (proc.stderr or proc.stdout).strip().replace("\n", " ")[:200]
        print(f"[fail] uid={uid} ({email}): {err}")
        failed += 1
        continue

    creds = json.loads(proc.stdout.strip())

    if dry_run:
        print(
            f"[dry-run] uid={uid} ({email}): "
            f"would sync profile {creds['profile_pk']}"
        )
        ok += 1
        continue

    import base64

    creds_b64 = base64.b64encode(json.dumps(creds).encode()).decode()
    update_php = f"""
$creds = json_decode(base64_decode('{creds_b64}'), TRUE);
$user = \\Drupal\\user\\Entity\\User::load({uid});
if (!$user) {{
  throw new \\RuntimeException('User {uid} not found');
}}
$user->set('field_backend_profile', $creds['profile_id']);
$user->set('field_backend_password', $creds['password']);
$user->save();
"""

    update = subprocess.run(
        ["docker", "exec", drupal, "drush", "php:eval", update_php],
        capture_output=True,
        text=True,
    )
    if update.returncode != 0:
        err = (update.stderr or update.stdout).strip().replace("\n", " ")[:200]
        print(f"[fail] uid={uid} ({email}): Drupal update failed: {err}")
        failed += 1
        continue

    print(f"[ok] uid={uid} ({email})")
    ok += 1

print("")
print(f"Done. processed={processed} ok={ok} skipped={skipped} failed={failed}")
sys.exit(1 if failed else 0)
PY
