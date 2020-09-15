ifeq ($(RUN_ON), docker)
	PATH_TO_THEME := ./themes/custom
else
	PATH_TO_THEME := ./public/themes/custom
endif

THEME_PACKAGE_JSON_EXISTS := $(shell test -f ./public/themes/custom/$(DRUPAL_THEME_NAME)/package.json && echo yes || echo no)

ifeq (${THEME_PACKAGE_JSON_EXISTS},yes)
	BUILD_TARGETS += build-theme
endif

ifeq ($(ENV),production)
	BUILD_THEME_ARGS := production
	NPM_BUILD_FLAG := --production
else
	BUILD_THEME_ARGS := development
	NPM_BUILD_FLAG :=
endif

PHONY += build-theme
build-theme: DOCKER_NODE_IMG ?= node:8.16.0-alpine
build-theme: WD := /usr/src/app
build-theme: ## Install NPM packages and build theme
ifeq ($(ENV),dev)
	$(call colorecho, "\n-Do npm ci for theme in specific node docker container...\n")
	@docker run --rm -v "$(CURDIR)":$(WD) -w $(WD) $(DOCKER_NODE_IMG) npm ci ${NPM_BUILD_FLAG} --prefix ./public/themes/custom/${DRUPAL_THEME_NAME} --engine-strict true
	$(call colorecho, "\n-Build theme for ${BUILD_THEME_ARGS} in specific node docker container...\n")
	@docker run --rm -v "$(CURDIR)":$(WD) -w $(WD) $(DOCKER_NODE_IMG) npm run --prefix ./public/themes/custom/${DRUPAL_THEME_NAME} gulp ${BUILD_THEME_ARGS}
else
	$(call colorecho, "\n-Do npm ci for theme on ${RUN_ON}...\n")
	$(call npm_on_${RUN_ON},ci ${NPM_BUILD_FLAG} --prefix ${PATH_TO_THEME}/${DRUPAL_THEME_NAME} --engine-strict true)
	$(call colorecho, "\n-Build theme for ${BUILD_THEME_ARGS} on ${RUN_ON}...\n")
	$(call npm_on_${RUN_ON},run --prefix ${PATH_TO_THEME}/${DRUPAL_THEME_NAME} gulp ${BUILD_THEME_ARGS})
endif
