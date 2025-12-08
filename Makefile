.PHONY: it
it: fix stan test docs ## Run the commonly used targets

.PHONY: help
help: ## Displays this list of targets with descriptions
	@grep --extended-regexp '^[a-zA-Z0-9_-]+:.*?## .*$$' $(firstword $(MAKEFILE_LIST)) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: setup
setup: vendor phpstan.neon ## Set up the project

.PHONY: fix
fix: rector php-cs-fixer prettier ## Automatic code fixes

.PHONY: rector
rector: vendor ## Automatic code fixes with Rector
	composer rector

.PHONY: php-cs-fixer
php-cs-fixer: ## Fix code style
	docker build --quiet --tag=graphql-php-cs-fixer --file=.php-cs-fixer.dockerfile .
	docker run --rm --volume="$(PWD):/app" --volume=/app/vendor graphql-php-cs-fixer sh -c "composer update --quiet && vendor/bin/php-cs-fixer fix"
	vendor/bin/php-cs-fixer fix

.PHONY: prettier
prettier: ## Format code with prettier
	prettier --write --tab-width=2 *.md **/*.md

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

.PHONY: bench
bench: ## Runs benchmarks with PHPBench
	composer bench

.PHONY: docs
docs: ## Generate the class-reference docs
	php generate-class-reference.php
	prettier --write docs/class-reference.md

vendor: composer.json composer.lock
	composer install
	composer validate
	composer normalize

composer.lock: composer.json
	composer update
