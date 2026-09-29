PHONY += sync-django-backend-credentials
sync-django-backend-credentials: ## Resync Drupal backend API credentials from Django profiles (DRUPAL_SYNC_UID, DRY_RUN=1, SYNC_LIMIT)
	$(call step,Sync Drupal field_backend_* credentials from Django...\n)
	@bash scripts/sync-django-backend-credentials.sh
