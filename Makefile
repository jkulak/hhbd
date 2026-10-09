# Everything runs in Docker or over ssh; nothing is installed on this Mac.
#
# OVH_HOST (and OVH_SSH_USER, ubuntu by default) come from gcloud-ovh-migrate's .env, which
# deploy/ovh/ovh.mk loads: the host's address is written down there, once.
SHELL := /usr/bin/env bash
.DEFAULT_GOAL := help

.PHONY: help
help: ## List every target
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) \
	  | awk 'BEGIN{FS=":.*?## "}{printf "  \033[1m%-22s\033[0m %s\n", $$1, $$2}'

# --- The OVH host -------------------------------------------------------------------------

# The shared host's own targets, word for word the same in every service on it: ovh-install,
# ovh-secrets-set, -show, -edit and -check, ovh-stack-test and ovh-logs.
include deploy/ovh/ovh.mk

.PHONY: ovh-db-up
ovh-db-up: ## Start only the database on the OVH host, so the data can go in before the first deploy
	ssh -o BatchMode=yes "$${OVH_SSH_USER:-ubuntu}@$${OVH_HOST:?OVH_HOST is not set}" \
	  'cd /srv/hhbd && sudo IMAGE_TAG=none SOPS_AGE_KEY_FILE=/etc/sops/age.key /usr/local/bin/sops exec-env hhbd.enc.env "docker compose up -d --wait db"'

.PHONY: ovh-ps
ovh-ps: ## Show hhbd's containers on the OVH host and their health
	ssh -o BatchMode=yes "$${OVH_SSH_USER:-ubuntu}@$${OVH_HOST:?OVH_HOST is not set}" \
	  'sudo docker ps -a --filter label=com.docker.compose.project=hhbd --format "table {{.Names}}\t{{.Image}}\t{{.Status}}"'

.PHONY: ovh-smoke
ovh-smoke: ## Smoke-test https://hhbd.pl on production's data, as a release is checked before it is kept
	./deploy/ovh/smoke.sh

.PHONY: ovh-migrate-status
ovh-migrate-status: ## Show which migrations production's database on the OVH host has applied
	MIGRATE_TARGET=ovh ./scripts/migrate.sh status

.PHONY: ovh-migrate
ovh-migrate: ## Apply the pending migrations to production's database, before the release that needs them
	MIGRATE_TARGET=ovh ./scripts/migrate.sh up

.PHONY: ovh-migrate-down
ovh-migrate-down: ## Revert the last applied migration on production's database, after typing a confirmation; N=2 for two
	MIGRATE_TARGET=ovh ./scripts/migrate.sh down $(or $(N),1)

.PHONY: ovh-migrate-baseline
ovh-migrate-baseline: ## Once: record the baseline on production's database, which already has its schema
	MIGRATE_TARGET=ovh ./scripts/migrate.sh baseline

# A one-off app container on the host with the content volume mounted read-only, for the
# backfills. `docker compose run -v` names a volume as Docker does, without the project prefix
# the compose file's `content` gets, and makes a new, empty volume of a name it does not know:
# hence hhbd_content by its full name, checked to exist first. DRY_RUN=1 reports and writes
# nothing.
OVH_CONTENT_RUN = ssh -o BatchMode=yes "$${OVH_SSH_USER:-ubuntu}@$${OVH_HOST:?OVH_HOST is not set}" \
	'cd /srv/hhbd && sudo docker volume inspect hhbd_content >/dev/null && sudo SOPS_AGE_KEY_FILE=/etc/sops/age.key /usr/local/bin/sops exec-env hhbd.enc.env "docker compose run --rm --no-deps -T -v hhbd_content:/var/www/html/content:ro app php /var/www/html/app/tools/$(1) backfill $(if $(DRY_RUN),--dry-run)"'

.PHONY: ovh-covers-backfill
ovh-covers-backfill: ## Describe production's covers in album_covers, in a one-off app container with the content volume read-only; DRY_RUN=1 to only report
	$(call OVH_CONTENT_RUN,covers.php)

.PHONY: ovh-photos-backfill
ovh-photos-backfill: ## Record the size, type and hash of production's artist photos, in a one-off app container with the content volume read-only; DRY_RUN=1 to only report
	$(call OVH_CONTENT_RUN,photos.php)

.PHONY: ovh-check-images
ovh-check-images: ## List the covers, photos and logos production's catalogue names but its content volume lacks
	DB_TARGET=ovh ./scripts/check-images.sh

.PHONY: ovh-import
ovh-import: ## Read an import batch into production's catalogue: make ovh-import BATCH=<dir> MODE=apply (a dry run without MODE)
	@./deploy/ovh/import.sh "$${BATCH:?BATCH is the batch directory}" $(or $(MODE),dry-run)

.PHONY: ovh-edit
ovh-edit: ## Edit production's catalogue, journalled: make ovh-edit DO="merge-albums 850 841" BY=<admin> WHY="..." (a dry run without MODE=apply); DO="undo <operation>" takes one back
	@./scripts/edit.sh ovh

.PHONY: ovh-import-runs
ovh-import-runs: ## List the last import runs on production's database, newest first; N=50 for more
	DB_TARGET=ovh ./scripts/import-runs.sh $(or $(N),20)

# --- Secrets ------------------------------------------------------------------------------
# ovh-secrets-set, -show, -edit and -check come with deploy/ovh/ovh.mk.

.PHONY: ovh-secrets-init
ovh-secrets-init: ## Create deploy/ovh/hhbd.enc.env once, with database passwords generated straight into it
	@[ ! -e $(OVH_SECRETS) ] || { echo "x $(OVH_SECRETS) exists; change a value with make ovh-secrets-set" >&2; exit 1; }
	@for key in DB_PASSWORD DB_ROOT_PASSWORD; do \
	  LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32 | ./scripts/secrets.sh set $(OVH_SECRETS) $$key || exit 1; \
	done
	@printf 'jkulak' | ./scripts/secrets.sh set $(OVH_SECRETS) GHCR_USER
	@echo "Next: make ovh-secrets-set KEY=GHCR_READ_TOKEN (a classic PAT with read:packages only)"

# --- The local database -------------------------------------------------------------------

.PHONY: reset-db
reset-db: ## Drop the local hhbd database and build it from the migrations and the test fixtures, or load the kept result of that (local stack only; RESET_DB_FULL=1 always builds)
	./scripts/reset-db.sh

.PHONY: test-images
test-images: ## Write the placeholder covers, photos and logos the fixtures name into content/, in the importer's image, the one with GD
	docker compose run --rm --no-deps -T --entrypoint php importer app/tools/generate-test-images.php

.PHONY: migrate
migrate: ## Apply the pending migrations to the local database
	./scripts/migrate.sh up

.PHONY: migrate-down
migrate-down: ## Revert the last applied migration on the local database; N=2 for the last two, N=all for every one
	./scripts/migrate.sh down $(or $(N),1)

.PHONY: migrate-status
migrate-status: ## Show which migrations the local database has applied and which are pending
	./scripts/migrate.sh status

.PHONY: migrate-new
migrate-new: ## Create the next migration's up and down files: make migrate-new NAME=add-album-isrc
	./scripts/migrate.sh new "$(NAME)"

.PHONY: migrate-baseline
migrate-baseline: ## Record the baseline on a local database that already has the schema, such as a loaded production dump
	./scripts/migrate.sh baseline

.PHONY: covers-backfill
covers-backfill: ## Describe the local covers on content/ in album_covers (size, hash, type); running it again adds nothing; DRY_RUN=1 to only report
	docker compose exec -T app php /var/www/html/app/tools/covers.php backfill $(if $(DRY_RUN),--dry-run)

.PHONY: photos-backfill
photos-backfill: ## Record the size, type and hash of the local artist photos on content/; running it again changes nothing; DRY_RUN=1 to only report
	docker compose exec -T app php /var/www/html/app/tools/photos.php backfill $(if $(DRY_RUN),--dry-run)

.PHONY: check-images
check-images: ## List the covers, photos and logos the local catalogue names but content/ lacks
	./scripts/check-images.sh

.PHONY: import
import: ## Read an import batch into the local catalogue and content/: make import BATCH=<dir> MODE=apply (a dry run without MODE)
	@COPYFILE_DISABLE=1 tar --no-xattrs -C "$${BATCH:?BATCH is the batch directory}" -cf - . | docker compose run --rm -T importer --$(or $(MODE),dry-run)

.PHONY: edit
edit: ## Edit the local catalogue, journalled: make edit DO="merge-albums 850 841" BY=<admin> WHY="..." (a dry run without MODE=apply); DO="undo <operation>" takes one back
	@./scripts/edit.sh local

.PHONY: import-runs
import-runs: ## List the last import runs on the local database, newest first; N=50 for more
	./scripts/import-runs.sh $(or $(N),20)

# --- Tests --------------------------------------------------------------------------------

# PHP runs in the stack's images, as this Mac has none: the importer's has GD, so the image tests
# run too, and neither needs the stack up. app/vendor first: docker compose exec app composer install
.PHONY: test-unit
test-unit: ## Run the unit tests, in the importer's image (it has GD); app/vendor installed first
	docker compose run --rm --no-deps -T --entrypoint php importer app/vendor/bin/phpunit -c app/tests/phpunit.xml

# The repository mounted whole, as the config is at its root; FILES= limits it to some files
PHP_CS_FIXER = docker compose run --rm --no-deps -T -v "$(CURDIR):/w" -w /w --entrypoint app/vendor/bin/php-cs-fixer app

.PHONY: cs
cs: ## Check the code style (PSR-12) with php-cs-fixer; FILES="app/..." for some files only
	$(PHP_CS_FIXER) fix --dry-run --diff --config=.php-cs-fixer.dist.php $(if $(FILES),--path-mode=intersection $(FILES))

.PHONY: cs-fix
cs-fix: ## Fix the code style with php-cs-fixer; FILES="app/..." for some files only
	$(PHP_CS_FIXER) fix --config=.php-cs-fixer.dist.php $(if $(FILES),--path-mode=intersection $(FILES))

.PHONY: hooks
hooks: ## Install the git hooks from app/hooks into this checkout (a worktree shares the main one's)
	cp app/hooks/pre-commit "$$(git rev-parse --git-common-dir)/hooks/pre-commit"
	chmod +x "$$(git rev-parse --git-common-dir)/hooks/pre-commit"

.PHONY: smoke
smoke: ## Smoke-test the local stack (docker compose up first): make smoke URL=http://localhost:8080
	./tests/smoke-test.sh $(or $(URL),http://localhost:8080)

# This repository's own test of the production stack, past what make ovh-stack-test checks in
# every service on the host.
.PHONY: ovh-e2e
ovh-e2e: ## Run deploy/ovh/compose.yaml here behind a stand-in edge: smoke test, client address, logs, an import
	./tests/ovh-e2e.sh

.PHONY: test-reset-db
test-reset-db: ## Check make reset-db against the running local stack
	./tests/reset-db-test.sh

.PHONY: test-migrate
test-migrate: ## Check the migration runner against the running local stack
	./tests/migrate-test.sh

.PHONY: test-schema
test-schema: ## Check the rules the migrations put on the schema (engines, audit columns) on the running local stack: make test-schema URL=http://localhost:8080
	./tests/schema-test.sh $(or $(URL),http://localhost:8080)
