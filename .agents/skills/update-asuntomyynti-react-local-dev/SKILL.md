---
name: update-asuntomyynti-react-local-dev
description: Updates asuntomyynti-react in local development and aligns Drupal asu_apartment_search module behavior. Use when updating the React widget, rebuilding local assets, or adjusting search filters.
---

# Update Asuntomyynti React (Local Dev)

## Quick context
- React app: `asuntomyynti-react/` (Vite + React 18, npm-based)
- Drupal module: `drupal-asuntotuotanto/public/modules/custom/asu_apartment_search/`
- React dev server: `npm run dev` (Vite). Node `>=24` is required (see `engines` in `package.json`).
- Drupal module updates are Composer-driven (root `composer.json` + `composer.lock`, then `composer install`).
- The container bind-mounts `asuntomyynti-react/dist` to `/asuntomyynti-react` (see `compose-dev.yaml`). Composer pulls the local zip from this path.

## Update workflow (React)
1. Ensure Node 24+ is active (project `engines.node` is `>=24`):
   - `nvm install 24 && nvm use 24`
2. Install dependencies:
   - `npm install`
   - The `husky` `prepare` script may fail under sandboxed shells (it tries to write `.git/config`). This is harmless for builds; install will still complete.
3. Build dist (zip + per-variant `react/{hitas,hitas_upcoming,haso,haso_upcoming}/`):
   - Preferred: `make asuntomyynti-react-rebuild-dist` (runs `npm run dist`)
   - Or manually: `nvm use 24 && npm run dist`
   - The build emits `dist/asuntomyynti-react-<VERSION>.zip` and per-variant folders under `dist/react/`.
   - If you hit errors, fix them and tell the human what you did.

## Drupal module update workflow (Composer)
1. Update `asuntomyynti-react/package.json` version number (only if releasing a new version of the widget).
2. Update the `asuntomyynti/react` package definition and `require` constraint in root `composer.json`.
3. Update the matching entry in `composer.lock`.
4. Inside the container, run:
   - `docker exec asuntotuotanto-app sh -c "cd /app && composer update asuntomyynti/react --no-scripts"`
   - This refreshes the content-hash and re-extracts the zip from `/asuntomyynti-react/`.
5. Re-sync module assets and clear caches:
   - `docker exec asuntotuotanto-app sh -c "cd /app && rsync -a vendor/asuntomyynti/react public/modules/custom/asu_apartment_search/assets"`
   - `docker exec asuntotuotanto-app sh -c "drush cr"`
   - (`composer install` without `--no-scripts` also runs the rsync via `post-install-cmd`.)

## Composer package paths
Use the local dist zip path (relative to the container) in both files.

`composer.json` (repositories section):
```
{
  "type": "package",
  "package": {
    "name": "asuntomyynti/react",
    "version": "<VERSION>",
    "dist": {
      "url": "/asuntomyynti-react/asuntomyynti-react-<VERSION>.zip",
      "type": "zip"
    }
  }
}
```

`composer.lock` (matching entry under `packages`):
```
{
  "name": "asuntomyynti/react",
  "version": "<VERSION>",
  "dist": {
    "type": "zip",
    "url": "/asuntomyynti-react/asuntomyynti-react-<VERSION>.zip"
  },
  "type": "library"
}
```

## Filters and query alignment
- When modifying filter behavior, ensure query params match `/elasticsearch` expectations.
- Keep project apartment widget filters aligned with the Drupal module's documented filters.

## Verification checklist
- `npm run dev` runs locally without errors (Vite dev server on `http://localhost:5173`).
- Widget renders and filters apply correctly.
- Local `/elasticsearch` queries return expected results.
- Drupal module assets are refreshed after composer update + rsync.
- `make asuntomyynti-react-check-sync` reports the expected version and the served `asu_react_main.js` length looks correct (~1 MB for current builds).

## If updates are not visible
1. Check the served module asset (note the path):
   - `/modules/custom/asu_apartment_search/assets/react/hitas/asu_react_main.js`
2. If the module asset is stale, ensure the dist mount is visible in the container:
   - `/asuntomyynti-react` should contain `asuntomyynti-react-<VERSION>.zip` and a `react/` folder.
   - The build runs `rimraf dist` which detaches the bind-mount inode; if the container shows an empty mount after a rebuild, restart the stack: `make asuntomyynti-react-restart-dev`.
3. Force reinstall in container:
   - Preferred: `make asuntomyynti-react-force-reinstall`
4. For a quick sync/debug check, run:
   - `make asuntomyynti-react-check-sync`

## Example triggers
- "Update asuntomyynti-react to the latest release locally."
- "Rebuild the React widget used by asu_apartment_search."
- "Adjust project apartments filters in local dev."
