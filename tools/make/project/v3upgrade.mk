PHONY += drush-major-remove-entities
drush-major-remove-entities: ## Remove entities of type article and gallery.
	$(call drush,edel paragraph --bundle=gallery_slide)
	$(call drush,edel paragraph --bundle=gallery)
	$(call drush,edel node --bundle=article)

PHONY += drush-major-update-config
drush-major-update-config: ## Update config.
	$(call drush,helfi:platform-config:update-config)

PHONY += drush-major-update-db
drush-major-update-db: ## Update database.
	$(call drush,helfi:platform-config:update-database)
