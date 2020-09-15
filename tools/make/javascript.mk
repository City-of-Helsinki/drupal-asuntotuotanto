ifeq ($(PACKAGE_JSON_EXISTS),yes)
	BUILD_TARGETS += node_modules
endif

node_modules: package.json ## Install NPM packages
	$(call step,Do npm install...)
	$(call npm_on_${RUN_ON},install --engine-strict true)

define npm_on_docker
	$(call docker_run_cmd,npm $(1))
endef

define npm_on_host
	@npm $(1)
endef
