# City of Helsinki - Asuntotuotanto

A City of Helsinki - Asuntotuotanto Drupal 9 website.

## React apartment search

The React application source lives in `frontend/asuntomyynti-react` and is built as part of the Drupal `make build` process. The build uses Node 24 in Docker and writes the four production bundles to the apartment-search module's ignored `assets/react` directory. No React release or Composer package is required.

For local development, start the Drupal stack first, then run `make asuntomyynti-react-dev`. This enables the local Vite HMR server at `https://localhost:5173`, clears Drupal caches, and serves React source directly while keeping API requests on the local Drupal container. Accept/trust the local development certificate in your browser if prompted. Stop the foreground process with Ctrl+C, then run `make asuntomyynti-react-dev-off` to restore the production bundle mode. React tests can be run with `make asuntomyynti-react-test`.

## Environments

| Env         | Branch  | Drush alias | URL                                                     |
| ----------- | ------- | ----------- | ------------------------------------------------------- |
| development | \*      | -           | https://asuntotuotanto.docker.so/                       |
| testing     | develop | -           | https://nginx-asuntotuotanto-test.agw.arodevtest.hel.fi |
| production  | main    | @main       | TBD                                                     |

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
