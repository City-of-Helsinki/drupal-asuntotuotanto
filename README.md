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
as-is. Use the sanitized dump command, which runs a read-only dump and rewrites
PII in the dump stream. Production rows are not updated.

### Create a dump (production)

On a production application pod (or any environment with real data):

```
drush asu:sql:sanitized-dump
```

Or from the project Makefile (writes under `SANITIZED_DUMP_DIR`, default `.`):

```
make create-sanitized-dump
```

This writes `sanitized_YYYYMMDDHHMMSS.sql` and keeps the four newest dumps in
that directory. The dump is written to a `.tmp` file first and renamed only when
it succeeds; older dumps are deleted only after that, so a failed dump leaves
existing ones untouched. Copy the file out of the pod afterwards. Do not commit
dump files (`*.sql` is gitignored).

Do **not** use `drush sql:sanitize` on production — that command updates live
rows. The command uses the `druidfi/gdpr-mysqldump` library directly; the
`gdpr_dumper` Drupal module (and its `drush sql:dump-gdpr`, which does not apply
the ASU rules) is not installed.

Data of session, log, cache, flood, login history, `authmap`, `honeypot_user`
(IP addresses) and `key_value_expire` (tempstore, e.g. draft forms) tables is
left out of the dump.

### Restore together with the backend

Drupal users are linked to backend profiles by Drupal `users.uuid` == Django
`Profile.id`, which both dumps keep. The masked copies in
`field_backend_profile` / `field_backend_password` must be recomputed for the
target environment:

1. Import this dump into MariaDB and the backend's sanitized dump into Postgres
   (see the [apartment-application-service README](https://github.com/City-of-Helsinki/apartment-application-service/blob/main/README.md#anonymized-database-dump)).
2. In the backend container, generate the fix and apply it here:

```
./manage.py drupal_restore_credentials_sql --i-know-this-is-a-restored-dump > fix.sql
drush sql:query --file=fix.sql
```

3. Run `drush cr`. Until step 2 is done, Drupal users cannot reach the backend.

An end-to-end check of this cycle against the local dev stacks is in
apartment-application-service:
[docs/sanitized-dump-restore-check.md](https://github.com/City-of-Helsinki/apartment-application-service/blob/main/docs/sanitized-dump-restore-check.md).

User passwords in the dump are set to `localdev`. To use another password, set
`ASU_SANITIZED_DUMP_PASSWORD_HASH` to a Drupal password hash when dumping, e.g.
from `drush php:eval "echo \Drupal::service('password')->hash('secret');"`.
A plain password in the variable is refused.

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
