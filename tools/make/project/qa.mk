TEST_TARGETS += lint-php

lint-php: ## Customized code style checking for PHP files
	$(call step,Check code style for PHP files...)
	@docker run --rm $(subst $(space),'',$(LINT_PATHS_PHP)) druidfi/drupal-qa:$(DRUPAL_VERSION) bash -c "phpcs -n . --ignore='*/elasticsearch_connector/*,*.css,*.md,node_modules'."
	$(call test_result,lint-php,"[OK]")

fix: ## Fix code style
	$(call step,Fix code with PHP Code Beautifier and Fixer...)
	@docker run --rm -it $(subst $(space),'',$(LINT_PATHS_PHP)) druidfi/drupal-qa:$(DRUPAL_VERSION) bash -c "phpcbf .  --ignore='*/elasticsearch_connector/*,*.css,*.md,node_modules'."
