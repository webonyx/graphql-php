.PHONY: it
it: fix stan test docs ## Run the commonly used targets

.PHONY: help
help: ## Displays this list of targets with descriptions
	@grep --extended-regexp '^\.?[a-zA-Z0-9_-]+:.*?## .*$$' $(firstword $(MAKEFILE_LIST)) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: setup
setup: vendor phpstan.neon ai-sync ## Set up the project

.PHONY: fix
fix: rector rector-benchmarks php-cs-fixer prettier ## Automatic code fixes

.PHONY: rector
rector: vendor ## Automatic code fixes with Rector
	composer rector

define run-php-cs-fixer
	docker build --quiet --tag="graphql-php-cs-fixer-$(1)" --build-arg="IMAGE=$(1)" php-cs-fixer
	docker run --rm --volume="$(PWD):/app" "graphql-php-cs-fixer-$(1)" $(2)
endef

.PHONY: php-cs-fixer
php-cs-fixer: ## Fix code style
	$(call run-php-cs-fixer,php:7.4-cli-bullseye)
	$(call run-php-cs-fixer,php:8.0-cli-bullseye,--path-mode=intersection src/Type/Definition/Deprecated.php src/Type/Definition/Description.php)
	$(call run-php-cs-fixer,php:8.1-cli-trixie,--path-mode=intersection benchmarks src/Type/Definition/PhpEnumType.php tests/Type/PhpEnumType)

.PHONY: prettier
prettier: ## Format code with prettier
	npx --yes prettier --write --tab-width=2 '*.md' '**/*.md'

phpstan.neon:
	printf "includes:\n  - phpstan.neon.dist" > phpstan.neon

.PHONY: stan
stan: ## Runs static analysis with PHPStan
	composer stan

.PHONY: baseline
baseline: ## Regenerate the PHPStan baseline
	composer baseline

.PHONY: test
test: ## Runs tests with PHPUnit
	composer test

ifdef GITHUB_ACTIONS
benchmarks-github-actions=--env=GITHUB_ACTIONS --env=GITHUB_WORKSPACE=/app --env=GITHUB_STEP_SUMMARY --volume="$(GITHUB_STEP_SUMMARY):$(GITHUB_STEP_SUMMARY)"
endif
run-benchmarks=docker build --quiet --tag=graphql-php-benchmarks benchmarks > /dev/null && docker run --rm --volume="$(CURDIR):/app" --user="$(shell id -u):$(shell id -g)" --env=COMPOSER_HOME=/tmp/composer $(benchmarks-github-actions) graphql-php-benchmarks

benchmarks/vendor: benchmarks/composer.json benchmarks/composer.lock
	$(run-benchmarks) composer validate --no-check-publish
	$(run-benchmarks) composer install
	touch benchmarks/vendor

.PHONY: bench
bench: benchmarks/vendor ## Counts benchmark instructions with Tally
	$(run-benchmarks) vendor/bin/tally run --output=tally-results

.PHONY: bench-accept
bench-accept: benchmarks/vendor ## Accepts the counts of the CI run RUN as expected
	$(if $(RUN),,$(error Pass the ID of the CI run, e.g. make bench-accept RUN=123))
	rm -rf benchmarks/tally-results
	gh run download $(RUN) --repo=webonyx/graphql-php --name=tally-results --dir=benchmarks/tally-results
	$(run-benchmarks) vendor/bin/tally accept tally-results

.PHONY: stan-benchmarks
stan-benchmarks: benchmarks/vendor ## Runs static analysis on the benchmarks with PHPStan
	$(run-benchmarks) vendor/bin/phpstan

.PHONY: rector-benchmarks
rector-benchmarks: benchmarks/vendor ## Automatic code fixes on the benchmarks with Rector
	$(run-benchmarks) vendor/bin/rector process

.PHONY: docs
docs: ## Generate the class-reference docs
	php generate-class-reference.php
	npx --yes prettier --write docs/class-reference.md

.PHONY: ai-sync
ai-sync: ## Generate local agent configuration from .ai
	npx --yes lnai@0.6.7 sync

vendor: composer.json composer.lock
	composer install
	composer validate
	composer normalize

composer.lock: composer.json
	composer update
