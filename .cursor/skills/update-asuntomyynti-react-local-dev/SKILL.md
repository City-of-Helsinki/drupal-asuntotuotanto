---
name: update-asuntomyynti-react-local-dev
description: Updates asuntomyynti-react in local development and aligns Drupal asu_apartment_search module behavior. Use when updating the React widget, rebuilding local assets, or adjusting search filters.
---

# Update Asuntomyynti React (Local Dev)

## Quick context
- React app: `asuntomyynti-react/`
- Drupal module: `drupal-asuntotuotanto/public/modules/custom/asu_apartment_search/`
- React dev server uses `yarn start` (Node 16 recommended if export errors occur).
- Drupal module updates are Composer-driven (root `composer.json` + `composer.lock`, then `composer install`).

## Update workflow (React)
1. Ensure Node 16 if you hit: `Package subpath './lib/tokenize' is not defined by "exports" ...`
   - `nvm install 16.0`
2. Install dependencies:
   - `yarn`
3. Update dist build:
   - `nvm use 16; yarn dist;`
   - If you run into errors, fix them and tell the human what you did
   - Ensure `drupal-asuntomyynti/compose-dev.yaml` mounts `asuntomyynti-react/dist` as `/asuntomyynti-react`.

## Drupal module update workflow (Composer)
1. Update `asuntomyynti-react/package.json` version number.
2. Update the `asuntomyynti-react` package version in root `composer.json`.
3. Update `composer.lock` accordingly.
4. Run:
   - `composer install`
5. Confirm the built React assets are updated in the Drupal module.

## Composer package paths
Use the local dist zip path in both files.

`composer.json`:
```
"package": {
  "name": "asuntomyynti/react",
  "version": "<VERSION>",
  "dist": {
    "url": "/asuntomyynti-react/asuntomyynti-react-<VERSION>.zip",
    "type": "zip"
  }
}
```

`composer.lock`:
```
{
  "name": "asuntomyynti/react",
  "version": "<VERSION>",
  "dist": {
    "type": "zip",
    "url": "ZIP FILE PATH IN dist/"
  },
  "type": "library"
}
```

## Filters and query alignment
- When modifying filter behavior, ensure query params match `/elasticsearch` expectations.
- Keep project apartment widget filters aligned with the Drupal module’s documented filters.

## Verification checklist
- `yarn start` runs locally without errors.
- Widget renders and filters apply correctly.
- Local `/elasticsearch` queries return expected results.
- Drupal module assets are refreshed after `composer install`.

## If updates are not visible
1. Check the served module asset (note the path):
   - `/modules/custom/asu_apartment_search/assets/react/hitas/asu_react_main.js`
2. If the module asset is stale, ensure the dist mount is visible in the container:
   - `/asuntomyynti-react` should contain the zip from `dist/`.
   - If empty, restart the stack: `docker compose -f compose-dev.yaml down && docker compose -f compose-dev.yaml up -d`
3. Force reinstall in container:
   - `docker exec asuntotuotanto-app sh -c "rm -rf /app/vendor/asuntomyynti/react && composer install"`
4. Clear caches:
   - `docker exec asuntotuotanto-app sh -c "drush cr"`

## Example triggers
- “Update asuntomyynti-react to the latest release locally.”
- “Rebuild the React widget used by asu_apartment_search.”
- “Adjust project apartments filters in local dev.”
