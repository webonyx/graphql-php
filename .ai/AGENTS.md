# AGENTS.md

This file provides guidance to coding agents when working with code in this repository.

## Project Overview

`webonyx/graphql-php` is a PHP implementation of the GraphQL specification.
As a foundational library, it requires no dependencies to install.
Supports PHP 7.4+.

## Development Workflow

The project uses Make targets as the primary workflow:

```bash
make setup          # Initial setup: install dependencies and generate agent config
make it             # Run commonly used checks (fix, stan, test, docs)
make fix            # Automatic code fixes (rector, php-cs-fixer, prettier)
make stan           # Static analysis with PHPStan
make test           # Run PHPUnit tests
make bench          # Run PHPBench benchmarks
make docs           # Generate class reference docs
```

## Public API

The following elements are part of the stable public API:

- Elements marked with `@api` in PHPDoc
- Constants listed in `/docs/class-reference.md`, generated for classes with `'constants' => true` in `generate-class-reference.php`

## Code and Testing Expectations

- Preserve backward compatibility for public APIs unless explicitly requested.
- Add or update tests when behavior changes.
- Keep changes focused and minimal.
- Run `make stan` and `make test` for behavioral changes.
- Use `make fix` for style/refactoring consistency when needed.
