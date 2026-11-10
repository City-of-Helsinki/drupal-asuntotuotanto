# City of Helsinki - Asuntotuotanto

A City of Helsinki - Asuntotuotanto Drupal 9 website.

## Environments

Env | Branch | Drush alias | URL
--- | ------ | ----------- | ---
development | * | - | https://asuntotuotanto.docker.so/
testing | develop | - | https://nginx-asuntotuotanto-test.agw.arodevtest.hel.fi
production | main | @main | TBD

## Requirements

You need to have these applications installed to operate on all environments:

- [Docker](https://github.com/druidfi/guidelines/blob/master/docs/docker.md)
- [Stonehenge](https://github.com/druidfi/stonehenge)

## Create and start the environment

For the first time (new project):

```
$ make new
```

And following times to create and start the environment:

```
$ make fresh
```

NOTE: Change these according of the state of your project.

## Login to Drupal container

This will log you inside the app container:

```
$ make shell
```

## Anonymized database dump

Production personal data must never be copied into local or shared environments
as-is. Copy production data only through the sanitized dump: it reads the
database, replaces personal data in the output, and never changes production
rows.

Drupal and the backend (apartment-application-service) each have their own
dump. Drupal users can only use the site together with their backend profiles,
so in almost every case you want **both**, made at about the same time, and
restored together (see [Restore together with the backend](#restore-together-with-the-backend)).

Do **not** use `drush sql:sanitize` or `drush sql:dump-gdpr` for this. The
first updates live rows, and the second doesn't apply the ASU rules.

### Create a dump

**On a server (OpenShift)**, run it in the Drupal pod and copy the file out:

```
oc exec <drupal-pod> -- drush asu:sql:sanitized-dump
# Prints: Sanitized dump saved to /tmp/drupal_sanitized_YYYYMMDDHHMMSS.sql
oc cp <drupal-pod>:/tmp/drupal_sanitized_YYYYMMDDHHMMSS.sql ./drupal_sanitized_YYYYMMDDHHMMSS.sql
oc exec <drupal-pod> -- rm /tmp/drupal_sanitized_YYYYMMDDHHMMSS.sql
```

Without `--directory` the dump goes to the system temp directory (`/tmp` on the
pods), because the Drupal root is not writable there. Pass
`--directory=<path>` to write it somewhere else, e.g. a mounted volume if `/tmp`
is too small.

**Locally**:

```
make create-sanitized-dump   # writes ./drupal_sanitized_*.sql (change with SANITIZED_DUMP_DIR)
```

How it behaves:

- The output file is `drupal_sanitized_YYYYMMDDHHMMSS.sql`. The five newest dumps in
  the directory are kept and older ones are deleted (change with `--keep`).
- A failed dump leaves no partial file and does not delete older dumps.
- The dump refuses to run if a custom table has a column without an
  anonymization rule (see [below](#what-is-kept-and-adding-fields)).
- Session, log, cache, flood, login history, `authmap`, `honeypot_user` (IP
  addresses) and `key_value_expire` (tempstore, e.g. draft forms) tables are
  dumped without data.
- All users' passwords become `localdev`. To use another password, set
  `ASU_SANITIZED_DUMP_PASSWORD_HASH` to a Drupal password hash when dumping,
  e.g. from `drush php:eval "echo \Drupal::service('password')->hash('secret');"`.
  A plain password in the variable is refused.
- Never commit dump files (`*.sql` is gitignored).

### Restore a Drupal dump locally

Put the dump file in the project directory, then:

```
make restore-sanitized-dump DUMP=drupal_sanitized_YYYYMMDDHHMMSS.sql
```

It shows the target database and asks you to type `yes` (`YES=1` skips the
prompt). Then it drops all tables, imports the dump and runs `drush deploy`
(database updates, config import, cache clear). `DUMP` must be a path inside
the project, because the container only sees the project directory.

You can now log in as any user with password `localdev`, e.g. via
`drush uli --name=<username>`. Customer SAML logins don't map to restored users.
Until you re-link them to the backend (next section), users can't see their
backend data.

### Restore together with the backend

Drupal users are linked to backend profiles by Drupal `users.uuid` == Django
`Profile.id`, and both dumps keep that link. Drupal also stores the profile id
and backend password in masked form (`field_backend_profile`,
`field_backend_password`), encoded with the **source** backend's
`HASHIDS_SALT`. Those values must be recomputed for the environment you restore
into, or no Drupal user can reach the backend.

1. Create the backend dump first and the Drupal dump right after it. Users
   created between the two dumps won't match.
2. Restore the backend dump (see the
   [apartment-application-service README](https://github.com/City-of-Helsinki/apartment-application-service#anonymized-database-dump))
   and the Drupal dump ([above](#restore-a-drupal-dump-locally)).
3. Re-link them, with both local dev stacks running:

   ```
   make relink-backend-credentials
   ```

   It runs `./manage.py drupal_restore_credentials_sql
   --i-know-this-is-a-restored-dump` in the Django container
   (`DJANGO_CONTAINER`, default `apartment-application-backend`), applies the
   output here and clears caches. The backend's `SANITIZED_DUMP_PASSWORD` must
   equal the value used when the backend dump was made.

On a test server, do step 3 by hand:
`oc exec <django-pod> -- ./manage.py drupal_restore_credentials_sql --i-know-this-is-a-restored-dump > fix.sql`,
then copy `fix.sql` into the Drupal pod with `oc cp` and run
`oc exec <drupal-pod> -- drush sql:query --file=/tmp/fix.sql` and
`oc exec <drupal-pod> -- drush cr`.

> **Never apply `fix.sql` to production.** It sets every Drupal user's backend
> password to `SANITIZED_DUMP_PASSWORD`.

To test the whole cycle against the local dev stacks, follow
[docs/sanitized-dump-restore-check.md](https://github.com/City-of-Helsinki/apartment-application-service/blob/main/docs/sanitized-dump-restore-check.md)
in apartment-application-service.

### What is kept, and adding fields

The rules live in `asu_sanitized_dump/src/Config/SanitizerConfig.php`. Kept on
purpose: ids and links to the backend (`users.uuid`, `field_backend_profile`,
`field_backend_id`), and `field_right_of_residence_number`, which drives HASO
queue ordering.

`SanitizerCoverageKernelTest` fails when a field of the user, application,
message or subscription entities has no rule. Add a rule in `expressions()` if
the field can hold personal data, otherwise add it to
`asu_sanitized_dump/data/safe_fields.yml`.

Custom tables outside entities (like `asu_application_co_applicant_map`) are
listed in `CUSTOM_TABLES` with their safe columns. Before dumping, the command
reads their live columns and refuses to run if any column has neither a rule
nor a safe listing. A new custom table that can hold personal data must be
added to `CUSTOM_TABLES` by hand.

## Audit logging

Audit logging uses the `AuditLog` of `helfi_api_base` together with
`php-resilient-logger`, the same way the Django backend does.

What gets logged:

- Create, update and delete of `node`, `user`, `asu_application`,
  `asu_application_payment` and `asu_application_message` entities. Updates
  of revisionable entities include a field diff. The list is
  `helfi_api_base.audit_log_entity_types` in
  `public/sites/default/services.yml`.
- User login and logout.
- Nothing is logged from CLI (drush), see `auditlog_entity_hooks_disable` in
  `settings.php`.

Entries are written to the `helfi_audit_logs` table. The cron script
`docker/openshift/crons/audit-log.sh` sends them to Elasticsearch every 15
minutes and clears sent entries older than 30 days:

```shell
drush helfi:audit-log:submit-unsent-entries
drush helfi:audit-log:clear-sent-entries
```

Sending is enabled when `AUDIT_LOG_ES_INDEX` is set:

| Variable | Description |
|---|---|
| `AUDIT_LOG_ES_URL` | Elasticsearch URL |
| `AUDIT_LOG_ES_INDEX` | Index / data stream (from Platta) |
| `AUDIT_LOG_ES_USERNAME` | Elasticsearch username |
| `AUDIT_LOG_ES_PASSWORD` | Elasticsearch password |
| `AUDIT_LOG_ENV` | Optional, `environment` field of the entries. Defaults to `APP_ENV`. |

Entries have `origin: ASUNTOTUOTANTO_DRUPAL` (Django uses
`APARTMENT_APPLICATION_SERVICE`). Without `AUDIT_LOG_ES_INDEX`, entries pile up
in the table and both commands do nothing. The table is dumped without data in
sanitized dumps.
