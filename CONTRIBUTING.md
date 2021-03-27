# Contributing to GraphQL PHP

## Workflow
If your contribution requires significant or breaking changes, or if you plan to propose a major new feature,
we recommend you to create an issue on the [GitHub](https://github.com/webonyx/graphql-php/issues) with
a brief proposal and discuss it with us first.

For smaller contributions just use this workflow:

* Fork the project.
* Add your features and or bug fixes.
* Add tests. Tests are important for us.
* Check your changes using `composer check`.
* Add an entry to the [Changelog's Unreleased section](CHANGELOG.md#unreleased).
* Send a pull request.

## Setup
First, copy the URL of your fork and `git clone` it to your local machine.

```sh
cd graphql-php
composer install
```

## Testing
We use [PHPUnit](https://phpunit.de/) to ensure the code works and continues to work as expected.

Run unit tests:
```sh
composer test
```

Some tests have an annotation such as `@see it('<description>')`.
It references a matching test in the [graphql-js implementation](https://github.com/graphql/graphql-js).

## Coding Standard
The coding standard of this project is based on [Doctrine CS](https://github.com/doctrine/coding-standard).

Run the inspections:
```sh
composer lint
```

Apply automatic code style fixes:
```sh
composer fix
```

## Static Analysis
We use [PHPStan](https://github.com/phpstan/phpstan) to validate code correctness.

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
We document this library in [docs](docs).

Generate the class reference docs:
```sh
composer docs
```
