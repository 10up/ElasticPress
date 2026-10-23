SHELL := /bin/bash

# Credentials and machine-specific paths live in .env, which is gitignored.
# Copy .env.example to .env to get started. Missing values are not fatal:
# targets that need them say so, and the ACF Pro license is always optional.
-include .env

# Database used by the PHPUnit suite. Override in .env.
WP_TESTS_DB_NAME ?= ep_wp_test
WP_TESTS_DB_USER ?= root
WP_TESTS_DB_PASS ?= root
WP_TESTS_DB_HOST ?= localhost
WP_TESTS_VERSION ?= latest

# WordPress and WooCommerce versions for the e2e environment. Empty means latest.
WP_VERSION ?=
WC_VERSION ?=

# Elasticsearch version for the local container. CI covers 7.10.1, 8.12.2 and
# 9.1.5, so set this in .env to reproduce a specific job.
ES_VERSION ?= 8.16.1

VERSION_ARGS = --wp-version='$(WP_VERSION)' --wc-version='$(WC_VERSION)'
EPIO_ARGS = --ep-host='$(EPIO_HOST)' --ep-credentials='$(EPIO_CREDENTIALS)' --ep-index-prefix='$(EPIO_INDEX_PREFIX)'

# Only passed when a license is configured, so the suite still runs without one.
ACF_ARGS = $(if $(ACF_PRO_LICENSE_KEY),--acf-pro-license='$(ACF_PRO_LICENSE_KEY)')

.DEFAULT_GOAL := help
.PHONY: help start-e2e start-e2e-epio setup-unit-tests setup-e2e-tests setup-e2e-tests-min setup-e2e-tests-epio setup-e2e-tests-epio-min destroy-e2e clean-db-cache require-epio

help:
	@echo "ElasticPress test environments"
	@echo
	@echo "  start-e2e                 Start wp-env and Elasticsearch $(ES_VERSION)"
	@echo "  start-e2e-epio            Start wp-env alone, for ElasticPress.io"
	@echo "  destroy-e2e               Destroy the wp-env environment and stop Elasticsearch"
	@echo
	@echo "  setup-e2e-tests           Set up e2e tests against local Elasticsearch"
	@echo "  setup-e2e-tests-min       Same, on the minimum supported WordPress and WooCommerce"
	@echo "  setup-e2e-tests-epio      Set up e2e tests against ElasticPress.io"
	@echo "  setup-e2e-tests-epio-min  Same, on the minimum supported WordPress and WooCommerce"
	@echo
	@echo "  setup-unit-tests          Install the PHPUnit test suite"
	@echo "  clean-db-cache            Delete the cached databases, forcing a full setup"
	@echo
	@echo "Start the environment before running a setup target."
	@echo "Versions and credentials are read from .env, see .env.example."

start-e2e:
	ES_VERSION='$(ES_VERSION)' npm run env:start

# ElasticPress.io needs no local Elasticsearch, so only wp-env is started.
start-e2e-epio: require-epio
	npm run env start
	npm run env:install-cli

setup-unit-tests:
	./bin/install-wp-tests.sh $(WP_TESTS_DB_NAME) $(WP_TESTS_DB_USER) $(WP_TESTS_DB_PASS) $(WP_TESTS_DB_HOST) $(WP_TESTS_VERSION)

setup-e2e-tests:
	npm run e2e:setup -- $(VERSION_ARGS) $(ACF_ARGS)

setup-e2e-tests-min:
	$(MAKE) setup-e2e-tests WP_VERSION=6.4 WC_VERSION=9.0.0

setup-e2e-tests-epio: require-epio
	npm run e2e:setup -- $(VERSION_ARGS) $(EPIO_ARGS) $(ACF_ARGS)

setup-e2e-tests-epio-min:
	$(MAKE) setup-e2e-tests-epio WP_VERSION=6.4 WC_VERSION=9.0.0

destroy-e2e:
	npm run env destroy -- --force
	npm run es:stop

clean-db-cache:
	rm -rf ./bin/.cache

require-epio:
	@if [ -z "$(EPIO_HOST)" ] || [ -z "$(EPIO_CREDENTIALS)" ] || [ -z "$(EPIO_INDEX_PREFIX)" ]; then \
		echo "ElasticPress.io credentials are missing."; \
		echo "Copy .env.example to .env and set EPIO_HOST, EPIO_CREDENTIALS, and EPIO_INDEX_PREFIX."; \
		exit 1; \
	fi
