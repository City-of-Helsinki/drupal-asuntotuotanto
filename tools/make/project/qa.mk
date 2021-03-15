TEST_TARGETS = asu-lint-php asu-lint-js
FIX_TARGETS = asu-lint-fix
LINT_PATHS_JS = /app/$(WEBROOT)/modules/custom/**/js/*
LINT_PATHS_JS += /app/$(WEBROOT)/themes/custom/**/src/js/*

asu-lint-php: ## Customized code style checking for PHP files
	$(call step,Check code style for PHP files...)
	@docker run --rm $(subst $(space),'',$(LINT_PATHS_PHP)) druidfi/drupal-qa:$(DRUPAL_VERSION) bash -c "phpcs -n . --ignore='*/elasticsearch_connector/*,*.css,*/helfi_*/*,*.md,node_modules'."
	$(call test_result,lint-php,"[OK]")

asu-lint-fix: ## Fix code style
	$(call step,Fix code with PHP Code Beautifier and Fixer...)
	@docker run --rm -it $(subst $(space),'',$(LINT_PATHS_PHP)) druidfi/drupal-qa:$(DRUPAL_VERSION) bash -c "phpcbf .  --ignore='*/elasticsearch_connector/*,*.css,*/helfi_*/*,*.md,node_modules'."

asu-lint-js: DOCKER_NODE_IMG ?= node:12.18-alpine
asu-lint-js: WD := /app
asu-lint-js: ## Check code style for JS files
	$(call step,Install linters...)
	@docker run --rm -v "$(CURDIR)":$(WD):cached -w $(WD) $(DOCKER_NODE_IMG) yarn --cwd $(WEBROOT)/core install
	$(call step,Check code style for JS files: $(LINT_PATHS_JS))
	@docker run --rm -v "$(CURDIR)":$(WD):cached -w $(WD) $(DOCKER_NODE_IMG) \
		$(WEBROOT)/core/node_modules/eslint/bin/eslint.js --resolve-plugins-relative-to ./$(WEBROOT)/core \
		--color --quiet --ignore-pattern '**/vendor/*' --ignore-pattern '**/node_modules/*' --ignore-pattern '**/dist/*' --ignore-pattern '**/elasticsearch_connector/*' \
		--no-error-on-unmatched-pattern --ext .js -c ./$(WEBROOT)/core/.eslintrc.json --global nav,moment,responsiveNav:true $(LINT_PATHS_JS)
