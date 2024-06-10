.PHONY: it
it: fix stan test docs ## Run the commonly used targets

.PHONY: help
help: ## Displays this list of targets with descriptions
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(firstword $(MAKEFILE_LIST)) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: setup
setup: vendor phpstan.neon ## Set up the project

.PHONY: fix
fix: rector php-cs-fixer ## Automatic code fixes

.PHONY: rector
rector: vendor ## Automatic code fixes with Rector
	composer rector

.PHONY: php-cs-fixer
php-cs-fixer: vendor ## Fix code style
	composer php-cs-fixer

phpstan.neon:
	printf "includes:\n  - phpstan.neon.dist" > phpstan.neon

.PHONY: stan
stan: ## Runs static analysis with phpstan
	composer stan

.PHONY: test
test: ## Runs tests with phpunit
	composer test

.PHONY: bench
bench: ## Runs benchmarks with phpbench
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
