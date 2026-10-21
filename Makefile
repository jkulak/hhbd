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

# Where coming back to hhbd starts. tests/make-how-test.sh holds every target it names to one
# make help lists.
.PHONY: how
how: ## How a change goes from the local stack to hhbd.pl, and how the catalogue is run there
	@printf '\n%s\n\n' 'A change, from the local stack to hhbd.pl'
	@printf '  %-2s %-22s %s\n' \
	  1. 'make up'               'Start the local stack, building what changed. In development mode' \
	  '' ''                      '(compose.override.example.yaml copied to compose.override.yaml' \
	  '' ''                      'once) it installs app/vendor too. make hooks, once a checkout.' \
	  2. 'make reset-db'         'A fresh database from the migrations and the fixtures, before the' \
	  '' ''                      'work and again after it.' \
	  3. 'make migrate-new'      'NAME=<slug> for any change to the schema or the data, then' \
	  '' ''                      'make migrate; make migrate-down takes it back.' \
	  4. 'make test-unit'        'Then make cs, and make smoke, make test-query-count and' \
	  '' ''                      'make test-schema, each with URL= set to the local stack.' \
	  5. 'make ovh-migrate'      'Before the merge, for a change that brings a migration;' \
	  '' ''                      'make ovh-install for one to the compose file, the secrets or the' \
	  '' ''                      'edge snippet, and then INSTALLED=1 for make ship.' \
	  6. 'make ship'             'PR=<n>, the merge, never gh pr merge: rebased onto main and' \
	  '' ''                      'signed, gated by its checks, pushed to main as a fast-forward;' \
	  '' ''                      'it follows the release, the deploy and production'"'"'s logs.' \
	  7. 'make ovh-smoke'        'The release'"'"'s smoke test again, by hand, then hhbd.pl itself for' \
	  '' ''                      'what the change does. make ovh-ps and make ovh-logs when' \
	  '' ''                      'something is off.'
	@printf '\n%s\n\n' 'The catalogue on hhbd.pl'
	@printf '  %-2s %-22s %s\n' \
	  1. 'make ovh-import'       'BATCH=<dir>, a dry run; MODE=apply reads it in. Its albums wait in' \
	  '' ''                      'the release queue, a few published every day.' \
	  2. 'make ovh-release-queue' 'What waits, and about when the last of it comes out.' \
	  3. 'make ovh-edit'         'DO="merge-albums 850 841" BY=<admin> WHY="...", a dry run;' \
	  '' ''                      'MODE=apply makes it, DO="undo <operation>" takes one back.' \
	  4. 'make ovh-dump'         'The production database to ~/Developer/hhbd-dumps, read-only.'
	@echo

# --- Merging ------------------------------------------------------------------------------

# Word for word gcloud-ovh-migrate's, as in every repo of mine (#271): its make service-drift
# DIR=<this checkout> ONLY=scripts/ship.sh says when the copy has moved.
.PHONY: ship
ship: ## Merge PR=<number>: rebased onto main and signed, gated, fast-forwarded onto main, then what the merge starts followed
	./scripts/ship.sh $(PR)

# --- The OVH host -------------------------------------------------------------------------

# The shared host's own targets, word for word the same in every service on it: ovh-install,
# ovh-secrets-set, -show, -edit and -check, ovh-stack-test and ovh-logs.
include deploy/ovh/ovh.mk

.PHONY: ovh-db-up
ovh-db-up: ## Start only the database on the OVH host, so the data can go in before the first deploy
	ssh -o BatchMode=yes "$${OVH_SSH_USER:-ubuntu}@$${OVH_HOST:?OVH_HOST is not set}" \
	  'cd /srv/hhbd && sudo IMAGE_TAG=none SOPS_AGE_KEY_FILE=/etc/sops/age.key /usr/local/bin/sops exec-env hhbd.enc.env "docker compose up -d --no-deps --wait postgres"'

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

.PHONY: ovh-indexnow
ovh-indexnow: ## Tell IndexNow every address in production's sitemaps, at the start and after a gap (#220); DRY_RUN=1 to only count them
	ssh -o BatchMode=yes "$${OVH_SSH_USER:-ubuntu}@$${OVH_HOST:?OVH_HOST is not set}" \
		'cd /srv/hhbd && sudo SOPS_AGE_KEY_FILE=/etc/sops/age.key /usr/local/bin/sops exec-env hhbd.enc.env "docker compose run --rm --no-deps -T app php /var/www/html/app/tools/indexnow.php all $(if $(DRY_RUN),--dry-run)"'

.PHONY: ovh-import
ovh-import: ## Read an import batch into production's catalogue: make ovh-import BATCH=<dir> MODE=apply (a dry run without MODE); its albums wait for the daily release, RELEASE=now publishes them at once
	@./deploy/ovh/import.sh "$${BATCH:?BATCH is the batch directory}" $(or $(MODE),dry-run)

# The release queue (#258), in a one-off app container of the running tag, as the hhbd-release
# timer runs the day's release.
OVH_RELEASE = ssh -o BatchMode=yes "$${OVH_SSH_USER:-ubuntu}@$${OVH_HOST:?OVH_HOST is not set}" \
	'cd /srv/hhbd && sudo SOPS_AGE_KEY_FILE=/etc/sops/age.key /usr/local/bin/sops exec-env hhbd.enc.env "docker compose run --rm --no-deps -T app php /var/www/html/app/tools/release.php $(1)"'

.PHONY: ovh-release-queue
ovh-release-queue: ## Show production's release queue: how many albums wait, by year, the next ten, and about when the last comes out
	@$(call OVH_RELEASE,queue)

.PHONY: ovh-release
ovh-release: ## Run the day's release on production by hand, as the hhbd-release timer does; N=3 for three albums, DRY_RUN=1 to only name them
	@$(call OVH_RELEASE,run $(if $(N),--count=$(N)) $(if $(DRY_RUN),--dry-run))

.PHONY: ovh-edit
ovh-edit: ## Edit production's catalogue, journalled: make ovh-edit DO="merge-albums 850 841" BY=<admin> WHY="..." (a dry run without MODE=apply); DO="undo <operation>" takes one back
	@./scripts/edit.sh ovh

.PHONY: ovh-dump
ovh-dump: ## Dump production's database to ~/Developer/hhbd-dumps/hhbd-prod-<date>.pg.sql.gz, outside every repository (read-only on production)
	@./scripts/ovh-dump.sh

.PHONY: ovh-import-runs
ovh-import-runs: ## List the last import runs on production's database, newest first; N=50 for more
	DB_TARGET=ovh ./scripts/import-runs.sh $(or $(N),20)

# --- Secrets ------------------------------------------------------------------------------
# ovh-secrets-set, -show, -edit and -check come with deploy/ovh/ovh.mk.

.PHONY: ovh-secrets-init
ovh-secrets-init: ## Create deploy/ovh/hhbd.enc.env once, with the database password generated straight into it
	@[ ! -e $(OVH_SECRETS) ] || { echo "x $(OVH_SECRETS) exists; change a value with make ovh-secrets-set" >&2; exit 1; }
	@for key in DB_PASSWORD; do \
	  LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32 | ./scripts/secrets.sh set $(OVH_SECRETS) $$key || exit 1; \
	done
	@printf 'jkulak' | ./scripts/secrets.sh set $(OVH_SECRETS) GHCR_USER
	@echo "Next: make ovh-secrets-set KEY=GHCR_READ_TOKEN (a classic PAT with read:packages only)"

# --- The local stack ----------------------------------------------------------------------

# Only the builder stage, development mode's, has composer: it fills app/vendor in the
# checkout's app/, which the stack mounts.
.PHONY: up
up: ## Start the local stack, building what changed; in development mode app/vendor gets the dev dependencies too
	docker compose up -d --build --wait
	@[ ! -f compose.override.yaml ] || docker compose exec -T app composer install --no-interaction

# --- The local database -------------------------------------------------------------------

.PHONY: reset-db
reset-db: ## Drop the local hhbd database and build it from the migrations and the test fixtures (local stack only)
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
import: ## Read an import batch into the local catalogue and content/: make import BATCH=<dir> MODE=apply (a dry run without MODE); its albums wait for the release, RELEASE=now publishes them at once
	@case "$(RELEASE)" in ''|now) ;; *) echo "x RELEASE is now or nothing, not '$(RELEASE)'" >&2; exit 2 ;; esac
	@COPYFILE_DISABLE=1 tar --no-xattrs -C "$${BATCH:?BATCH is the batch directory}" -cf - . | docker compose run --rm -T importer --$(or $(MODE),dry-run) $(if $(RELEASE),--release-now)

.PHONY: release-queue
release-queue: ## Show the local release queue: how many albums wait, by year, the next ten, and about when the last comes out
	@docker compose exec -T app php /var/www/html/app/tools/release.php queue

.PHONY: release
release: ## Run a day's release on the local catalogue; N=3 for three albums, DRY_RUN=1 to only name them
	@docker compose exec -T app php /var/www/html/app/tools/release.php run $(if $(N),--count=$(N)) $(if $(DRY_RUN),--dry-run)

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

.PHONY: test-query-count
test-query-count: ## Check each page sends at most 25 queries, however many rows it lists, on the running local stack: make test-query-count URL=http://localhost:8080
	./tests/query-count-test.sh $(or $(URL),http://localhost:8080)
