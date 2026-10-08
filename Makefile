# Everything runs in Docker or over ssh; nothing is installed on this Mac.
#
# OVH_HOST (and OVH_SSH_USER, ubuntu by default) come from the environment or from .env,
# which is never committed. See .env.example.
SHELL := /usr/bin/env bash
.DEFAULT_GOAL := help

-include .env
export OVH_HOST OVH_SSH_USER

SECRETS_FILE := deploy/hhbd.enc.env

.PHONY: help
help: ## List every target
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) \
	  | awk 'BEGIN{FS=":.*?## "}{printf "  \033[1m%-18s\033[0m %s\n", $$1, $$2}'

# --- The OVH host -------------------------------------------------------------------------

.PHONY: ovh-install
ovh-install: ## Install the compose file, hhbd.enc.env and the hhbd.pl edge snippet on the OVH host, and reload the edge
	./deploy/ovh-install.sh

.PHONY: ovh-db-up
ovh-db-up: ## Start only the database on the OVH host, so the data can go in before the first deploy
	ssh -o BatchMode=yes "$${OVH_SSH_USER:-ubuntu}@$${OVH_HOST:?OVH_HOST is not set}" \
	  'cd /srv/hhbd && sudo IMAGE_TAG=none SOPS_AGE_KEY_FILE=/etc/sops/age.key /usr/local/bin/sops exec-env hhbd.enc.env "docker compose up -d --wait db"'

.PHONY: ovh-ps
ovh-ps: ## Show hhbd's containers on the OVH host and their health
	ssh -o BatchMode=yes "$${OVH_SSH_USER:-ubuntu}@$${OVH_HOST:?OVH_HOST is not set}" \
	  'sudo docker ps -a --filter label=com.docker.compose.project=hhbd --format "table {{.Names}}\t{{.Image}}\t{{.Status}}"'

.PHONY: ovh-data
ovh-data: ## Copy the database and content/ from the Google VM to the OVH host, then verify both
	./deploy/ovh-data.sh all

.PHONY: ovh-data-check
ovh-data-check: ## Compare the database (exact counts) and content/ (files, bytes) on Google and OVH
	./deploy/ovh-data.sh db-check
	./deploy/ovh-data.sh content-check

.PHONY: ovh-smoke
ovh-smoke: ## Smoke-test hhbd.pl on the OVH host directly, before the DNS points at it
	SMOKE_CURL_OPTS="--connect-to hhbd.pl:443:$${OVH_HOST:?OVH_HOST is not set}:443 --insecure" ./tests/smoke-test.sh https://hhbd.pl

# --- Secrets ------------------------------------------------------------------------------

.PHONY: secrets-check
secrets-check: ## Fail if a plaintext secret or a private key is about to be committed
	./scripts/secrets-check.sh

.PHONY: secrets-init
secrets-init: ## Create deploy/hhbd.enc.env once, with database passwords generated straight into it
	@[ ! -e $(SECRETS_FILE) ] || { echo "x $(SECRETS_FILE) exists; change a value with make secrets-set" >&2; exit 1; }
	@for key in DB_PASSWORD DB_ROOT_PASSWORD; do \
	  LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32 | ./scripts/secrets.sh set $(SECRETS_FILE) $$key || exit 1; \
	done
	@printf 'jkulak' | ./scripts/secrets.sh set $(SECRETS_FILE) GHCR_USER
	@echo "Next: make secrets-set KEY=GHCR_READ_TOKEN (a classic PAT with read:packages only)"

.PHONY: secrets-set
secrets-set: ## Set one value from a hidden prompt: make secrets-set KEY=NAME
	./scripts/secrets.sh set $(SECRETS_FILE) "$(KEY)"

.PHONY: secrets-show
secrets-show: ## List the variable names in deploy/hhbd.enc.env, never their values
	./scripts/secrets.sh show $(SECRETS_FILE)

.PHONY: secrets-edit
secrets-edit: ## Edit deploy/hhbd.enc.env in place (vi in a container)
	./scripts/secrets.sh edit $(SECRETS_FILE)

# --- Tests --------------------------------------------------------------------------------

.PHONY: smoke
smoke: ## Smoke-test the local stack (docker compose up first): make smoke URL=http://localhost:8080
	./tests/smoke-test.sh $(or $(URL),http://localhost:8080)

.PHONY: test-ovh-data
test-ovh-data: ## Run the data move against two throwaway local databases and check it
	./tests/ovh-data-test.sh

.PHONY: test-ovh-release
test-ovh-release: ## Run every path of a release against a stand-in ci-deploy (no Docker, no network)
	./tests/ovh-release-test.sh

.PHONY: test-ovh-stack
test-ovh-stack: ## Run deploy/compose.ovh.yaml locally behind a stand-in edge and check it
	./tests/ovh-stack-test.sh
