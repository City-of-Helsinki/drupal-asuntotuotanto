---
name: fix-composer-audit
description: Runs composer audit --abandoned=ignore and resolves reported PHP dependency vulnerabilities. Use when fixing composer audit failures, security advisories in composer.lock, or CI "Scan security updates" step failures.
---

# Fix Composer Audit Problems

run composer audit --abandoned=ignore and fix any vulnerabilities

## Execution context

- Project root has `composer.json` and `composer.lock`.
- CI runs audit after `composer install` (see `.github/workflows/test.yml`).
- Local commands run inside the app container:

```bash
docker exec asuntotuotanto-app sh -c "cd /app && composer audit --abandoned=ignore"
```

Use `--format=json` when you need structured output for triage:

```bash
docker exec asuntotuotanto-app sh -c "cd /app && composer audit --abandoned=ignore --format=json"
```

## Workflow

1. **Audit** — Run `composer audit --abandoned=ignore`. If clean, stop.
2. **Triage** — For each advisory, note package name, installed version, and patched/fixed version.
3. **Trace** — Find why the vulnerable version is installed:

```bash
docker exec asuntotuotanto-app sh -c "cd /app && composer why vendor/package"
docker exec asuntotuotanto-app sh -c "cd /app && composer why-not vendor/package:FIXED_VERSION"
```

4. **Fix** — Apply the smallest change that resolves the advisory (see strategies below).
5. **Reinstall** — Run `composer update` for affected packages, then `composer install --no-interaction` to verify lock consistency.
6. **Re-audit** — Run `composer audit --abandoned=ignore` again until it passes.
7. **Verify** — Run project checks (see Verification).

## Fix strategies

Prefer updating to a patched release over ignoring advisories.

### Direct dependency

Package is listed in root `composer.json` `require` or `require-dev`:

1. Widen or bump the constraint in `composer.json` if it blocks the fixed version.
2. Update only that package and its dependencies:

```bash
docker exec asuntotuotanto-app sh -c "cd /app && composer update vendor/package --with-all-dependencies"
```

### Transitive dependency

Package is pulled in by another dependency:

1. Update the parent package that owns the constraint.
2. If the parent has no fixed release yet, check upstream issues/PRs for a compatible version bump.
3. As a last resort for Drupal ecosystem packages, check whether `drupal/core-recommended` or another metapackage pin can be updated safely.

### Drupal core and Symfony

- Core is pinned via `drupal/core`, `drupal/core-recommended`, and `drupal/core-composer-scaffold` (keep versions aligned, e.g. `~11.3.10`).
- Many Symfony packages come through core; fixing them often means updating Drupal core rather than requiring Symfony directly.

### Patches

If a security fix requires a local patch:

1. Add or update the patch under `patches/` and reference it in `composer.json` `extra.patches`.
2. Refresh `patches.lock.json` after creating or editing patches.
3. Run `composer install` and confirm patches apply (`composer-exit-on-patch-failure` is enabled).

### When no fix exists

Document the blocker for the human: package, advisory ID, constraint chain from `composer why`, and upstream status. Do not add `audit.ignore` entries unless the user explicitly approves accepting the risk.

## Verification

After audit passes:

```bash
docker exec asuntotuotanto-app sh -c "cd /app && composer install --no-interaction"
docker exec asuntotuotanto-app sh -c "cd /app && composer audit --abandoned=ignore"
docker exec asuntotuotanto-app sh -c "composer test-php public/modules/custom"
docker exec asuntotuotanto-app sh -c "drush cr"
```

Run `make lint-drupal` when PHP files under `public/modules/custom` were changed.

## Output

Summarize for the human:

- Advisories found and fixed (package, old version, new version).
- `composer.json` constraint changes, if any.
- Patches added or refreshed, if any.
- Verification commands run and their results.
- Any remaining advisories that could not be fixed and why.

## Example triggers

- "Fix composer audit failures."
- "CI Scan security updates step is failing."
- "Update dependencies to resolve security advisories in composer.lock."
