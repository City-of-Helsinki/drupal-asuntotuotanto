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
that directory. Copy the file out of the pod afterwards. Do not commit dump
files (`*.sql` is gitignored).

Do **not** use `drush sql:sanitize` on production — that command updates live
rows. Prefer `drush asu:sql:sanitized-dump` / `drush sql-dump-gdpr` for sharing.

After importing a sanitized dump locally, user passwords are set to `localdev`.

Note: use `drush asu:sql:sanitized-dump` (or `make create-sanitized-dump`), not
`drush sql:dump-gdpr` alone — the ASU PII map is applied by the custom command.
