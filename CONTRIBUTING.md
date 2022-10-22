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
git clone <your-fork-url>
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

When porting tests that utilize [the `dedent()` test utility from `graphql-js`](https://github.com/graphql/graphql-js/blob/99d6079434/src/__testUtils__/dedent.js),
we instead use [the PHP native `nowdoc` syntax](https://www.php.net/manual/en/language.types.string.php#language.types.string.syntax.nowdoc).
If the string contents are in a specific grammar, use an appropriate tag such as `GRAPHQL`, `PHP` or `JSON`:

```php
self::assertSomePrintedOutputExactlyMatches(
    <<<'GRAPHQL'
    type Foo {
      bar: Baz
    }

    GRAPHQL,
    $output
);
```

## Coding Standard

We format the code automatically with [php-cs-fixer](https://github.com/friendsofphp/php-cs-fixer).

Apply automatic code style fixes:

```sh
composer fix
```

### Multiline Ternary Expressions

Ternary expressions must be spread across multiple lines.

```php
$foo = $cond
    ? 1
    : 2;
```

### Extensibility

We cannot foresee every possible use case in advance, extending the code should remain possible.

#### `protected` over `private`

Always use class member visibility `protected` over `private`.

#### `final` classes

Prefer `final` classes in [tests](tests), but never use them in [src](src).

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

### Type Assertions

When control flow or native types are insufficient to convince the IDE or PHPStan that a value
is of a certain type, but you know it must be due to some invariant, you may assert its type.
Prefer `assert()` for simple types and only use `@var` for complex types:

```php
function identity($value) { return $value; }

$mustBeInt = identity(1);
assert(is_int($mustBeInt));

/** @var array<string, int> $mustBeArrayOfStrings */
$mustBeArrayOfStringsToInts = identity(['foo' => 42]);
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
composer docs
```
