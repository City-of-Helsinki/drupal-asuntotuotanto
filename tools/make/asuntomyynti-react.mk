PHONY += asuntomyynti-react-check-sync
asuntomyynti-react-check-sync: ## Check React widget assets vs Drupal and version match
	$(call step,Checking React widget assets vs Drupal...\n)
	@COMPOSER_VERSION=$$(composer show asuntomyynti/react 2>/dev/null | awk '/^versions/ {print $$4}' | sed 's/,.*//'); \
	if [ -z "$$COMPOSER_VERSION" ]; then \
	  echo "Could not resolve asuntomyynti/react version from Composer (asuntomyynti/react)" >&2; \
	  exit 1; \
	fi; \
	ASSET_PATH="/modules/custom/asu_apartment_search/assets/react/hitas/asu_react_main.js?v=$$COMPOSER_VERSION"; \
	echo "Composer asuntomyynti/react version: $$COMPOSER_VERSION"; \
	echo "Expected asset path: $$ASSET_PATH"; \
	echo "Fetching asset to inspect size..."; \
	LENGTH=$$(curl -sk "https://asuntotuotanto.docker.so$$ASSET_PATH" | wc -c); \
	echo "Length: $$LENGTH"

PHONY += asuntomyynti-react-restart-dev
asuntomyynti-react-restart-dev: ## Restart dev Docker stack to remount asuntomyynti-react dist
	$(call step,Restart dev stack for asuntomyynti-react...\n)
	docker compose -f compose-dev.yaml down
	docker compose -f compose-dev.yaml up -d
	$(call sub_step,Checking /asuntomyynti-react mount in container...\n)
	docker exec asuntotuotanto-app sh -c "ls -al /asuntomyynti-react || echo 'no mount'"

PHONY += asuntomyynti-react-force-reinstall
asuntomyynti-react-force-reinstall: ## Force clean reinstall of asuntomyynti/react in container and clear caches
	$(call step,Force reinstall asuntomyynti/react in container...\n)
	docker exec asuntotuotanto-app sh -c "composer clear-cache && rm -rf /app/vendor/asuntomyynti/react && composer install && drush cr"
