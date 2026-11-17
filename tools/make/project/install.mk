ifeq ($(DRUPAL_CONF_EXISTS),yes)
	DRUPAL_NEW_TARGETS := up build drush-si drush-cr drush-locale-update drush-uli
else
	DRUPAL_NEW_TARGETS := up build drush-si helfi-drush-enable-modules drush-locale-update drush-uli
endif
DRUPAL_POST_INSTALL_TARGETS := drush-deploy drush-locale-update drush-uli

OC_LOGIN_TOKEN ?= $(shell bash -c 'read -s -p "You must obtain an API token by visiting https://oauth-openshift.apps.arodevtest.hel.fi/oauth/token/request (Token):" token; echo $$token')
# SYNC_TARGETS := oc-login oc-sync

PHONY += oc-login
oc-login:
	$(call drush,helfi:oc:login $(OC_LOGIN_TOKEN))

PHONY += oc-sync
oc-sync:
	$(call drush,helfi:oc:get-dump)
	$(call drush,sql-query --file=${DOCKER_PROJECT_ROOT}/$(DUMP_SQL_FILENAME),SQL dump imported)
	$(call drush,helfi:oc:sanitize-database)
	$(call drush,cr)
	$(call drush,cim -y)
	$(call drush,cr)

PHONY += helfi-drush-enable-modules
helfi-drush-enable-modules: ## Enable modules and base configurations.
	$(call step,Install base configurations...)
	$(call drush,cr)
	$(call drush,en -y helfi_platform_config helfi_base_config)

SANITIZED_DUMP_DIR ?= .
# DOCKER_PROJECT_ROOT comes from docker.mk, which is only included where a
# docker binary exists (the host). Inside the container, use make's own
# working directory instead. Absolute SANITIZED_DUMP_DIR values are used as-is.
SANITIZED_DUMP_PATH = $(if $(filter /%,$(SANITIZED_DUMP_DIR)),$(SANITIZED_DUMP_DIR),$(or $(DOCKER_PROJECT_ROOT),$(CURDIR))/$(SANITIZED_DUMP_DIR))

PHONY += create-sanitized-dump
create-sanitized-dump: ## Create anonymized SQL dump (does not modify live DB).
	$(call step,Create sanitized dump in $(SANITIZED_DUMP_PATH)...)
	$(call drush,asu:sql:sanitized-dump --directory=$(SANITIZED_DUMP_PATH))

# DUMP is relative to the project root, so the same path works on the host and
# in the container, where the project is mounted at DOCKER_PROJECT_ROOT.
# A literal comma would split the $(call drush,...) arguments.
SANITIZED_COMMA := ,
PHONY += restore-sanitized-dump
restore-sanitized-dump: ## Replace the database with a sanitized dump (DUMP=file.sql, YES=1 skips the prompt).
	@test -n "$(DUMP)" || { echo "Usage: make restore-sanitized-dump DUMP=file.sql [YES=1]" >&2; exit 1; }
	@case "$(DUMP)" in /*|../*) echo "DUMP must be a path inside the project, e.g. DUMP=drupal_sanitized_20261104120000.sql" >&2; exit 1;; esac
	@test -s "$(DUMP)" || { echo "Dump file $(DUMP) does not exist or is empty." >&2; exit 1; }
	$(call step,This drops all tables in this Drupal database and imports $(DUMP):)
	$(call drush,status --fields=db-hostname$(SANITIZED_COMMA)db-name)
	@test -n "$(YES)" || { read -r -p "Type 'yes' to continue: " answer; [ "$$answer" = yes ] || { echo "Aborted." >&2; exit 1; }; }
	$(call drush,sql-drop --quiet -y)
	$(call step,Import $(DUMP)...)
	$(call drush,sql-query --file=$(or $(DOCKER_PROJECT_ROOT),$(CURDIR))/$(DUMP))
	$(call drush,deploy -y)
	@echo "Restored $(DUMP). Users' passwords are localdev unless ASU_SANITIZED_DUMP_PASSWORD_HASH was set when dumping."
	@echo "Run 'make relink-backend-credentials' after restoring the backend dump."

DJANGO_CONTAINER ?= apartment-application-backend

PHONY += relink-backend-credentials
relink-backend-credentials: ## Re-link restored Drupal users to the restored local backend (never in production).
	$(call step,Recompute field_backend_* with $(DJANGO_CONTAINER)'s HASHIDS_SALT...)
	@fix=$$(mktemp) && \
	docker exec $(DJANGO_CONTAINER) ./manage.py drupal_restore_credentials_sql --i-know-this-is-a-restored-dump > "$$fix" && \
	docker compose exec -T $(CLI_SERVICE) drush sql:cli < "$$fix"; \
	rc=$$?; rm -f "$$fix"; exit $$rc
	$(call drush,cr)

PHONY += drush-locale-update
drush-locale-update: ## Update translations.
	$(call step,Update translations...)
	$(call drush,state:set locale.translation_last_checked 0)
	$(call drush_on_docker,locale:check)
	$(call drush_on_docker,locale:update)
	$(call drush_on_docker,cr)
	$(call step,Import custom translations...)
	$(call drush,helfi:locale-import helfi_platform_config)
