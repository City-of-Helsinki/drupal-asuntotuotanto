BUILD_TARGETS += build-asuntomyynti-react

ASUNTOMYYNTI_REACT_DIR := frontend/asuntomyynti-react
ASUNTOMYYNTI_REACT_ASSETS := public/modules/custom/asu_apartment_search/assets/react

.PHONY: build-asuntomyynti-react
build-asuntomyynti-react: ## Build React widgets into Drupal module assets
	$(call step,Building asuntomyynti-react for Drupal...\n)
	@mkdir -p "$(HOME)/.npm"
	@docker run --rm --user "$$(id -u):$$(id -g)" -v "$(CURDIR):/app" -v "$(HOME)/.npm:/npm-cache" -w "/app/$(ASUNTOMYYNTI_REACT_DIR)" node:24-alpine sh -c 'npm ci --cache /npm-cache && npm run drupal:build'
	@rm -rf "$(ASUNTOMYYNTI_REACT_ASSETS)"
	@mkdir -p "$(ASUNTOMYYNTI_REACT_ASSETS)"
	@cp -R "$(ASUNTOMYYNTI_REACT_DIR)/dist/react/." "$(ASUNTOMYYNTI_REACT_ASSETS)/"
	$(call sub_step,React assets copied to $(ASUNTOMYYNTI_REACT_ASSETS)\n)

.PHONY: asuntomyynti-react-install
asuntomyynti-react-install: ## Install React dependencies using Node 24 in Docker
	@mkdir -p "$(HOME)/.npm"
	@docker run --rm --user "$$(id -u):$$(id -g)" -v "$(CURDIR):/app" -v "$(HOME)/.npm:/npm-cache" -w "/app/$(ASUNTOMYYNTI_REACT_DIR)" node:24-alpine sh -c 'npm ci --cache /npm-cache'

.PHONY: asuntomyynti-react-test
asuntomyynti-react-test: ## Run React tests using Node 24 in Docker
	@mkdir -p "$(HOME)/.npm"
	@docker run --rm --user "$$(id -u):$$(id -g)" -v "$(CURDIR):/app" -v "$(HOME)/.npm:/npm-cache" -w "/app/$(ASUNTOMYYNTI_REACT_DIR)" node:24-alpine sh -c 'npm ci --cache /npm-cache && npm run test -- --run'

.PHONY: asuntomyynti-react-dev
asuntomyynti-react-dev: ## Start React Vite HMR server for the local Drupal site
	@APP_ENV=dev ASUNTOMYYNTI_REACT_DEV_SERVER=https://localhost:5173 docker compose up -d app
	@docker compose exec app drush cr
	@mkdir -p "$(HOME)/.npm"
	@docker run --rm --user "$$(id -u):$$(id -g)" --network stonehenge-network -e VITE_BACKEND_ORIGIN="http://$(COMPOSE_PROJECT_NAME)-app:8080" -e VITE_DEV_ORIGIN="https://$(DRUPAL_HOSTNAME)" -p 5173:5173 -v "$(CURDIR):/app" -v "$(HOME)/.npm:/npm-cache" -w "/app/$(ASUNTOMYYNTI_REACT_DIR)" node:24-alpine sh -c 'npm ci --cache /npm-cache && npm run dev:drupal'

.PHONY: asuntomyynti-react-dev-off
asuntomyynti-react-dev-off: ## Disable Vite HMR and restore production React assets in Drupal
	@APP_ENV=dev ASUNTOMYYNTI_REACT_DEV_SERVER= docker compose up -d app
	@docker compose exec app drush cr