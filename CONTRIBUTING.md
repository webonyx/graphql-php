# Contributing to graphql-php

## Workflow

If your contribution requires significant or breaking changes, or if you plan to propose a major new feature,
we recommend you to [create an issue](https://github.com/webonyx/graphql-php/issues/new)
with a brief proposal and discuss it with us first.

For smaller contributions just use this workflow:

- Fork the project.
- Add your features and or bug fixes.
- Add tests. Tests are important for us.
- Check your changes using `composer check`.
- Add an entry to the [Changelog's Unreleased section](CHANGELOG.md#unreleased).
- Send a pull request.

## Setup

```sh
git clone <your-fork>
cd graphql-php
composer install
```

## Testing

We ensure the code works and continues to work as expected with [PHPUnit](https://phpunit.de).

Run unit tests:

```sh
composer test
```

Some tests have an annotation such as `@see it('<description>')`.
It references a matching test in the [graphql-js implementation](https://github.com/graphql/graphql-js).

## Coding Standard

We check and fix the coding standard with [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer).

Run the inspections:

```sh
composer lint
```

Apply automatic code style fixes:

```sh
composer fix
```

## Static Analysis

We validate code correctness with [PHPStan](https://phpstan.org).

Run static analysis:

```sh
composer stan
```

Regenerate the [PHPStan baseline](https://phpstan.org/user-guide/baseline):

```sh
composer baseline
```

## Running Benchmarks

We benchmark performance critical code with [PHPBench](https://github.com/phpbench/phpbench).

Check performance:

```sh
composer bench
```

## Documentation

We document this library by rendering the Markdown files in [docs](docs) with [MkDocs](https://www.mkdocs.org).

Generate the class reference docs:

```sh
composer api-docs
```
