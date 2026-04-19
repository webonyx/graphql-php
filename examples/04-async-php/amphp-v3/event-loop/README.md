## GraphQL with AMPHP v3 (Fiber-based)

This example uses [AMPHP v3](https://amphp.org) with fiber-based async execution via `AmpFutureAdapter`.

### Dependencies

```sh
composer require amphp/amp:^3
```

### Run locally

```sh
php -S localhost:8080 graphql.php
```

### Make a request

```sh
curl --data '{"query": "query { product article }"}' \
     --header "Content-Type: application/json" \
     localhost:8080
```

### Migrating from AMPHP v2

If you were using the `AmpPromiseAdapter` with AMPHP v2, switch to `AmpFutureAdapter` with AMPHP v3:

```php
// Before (amphp/amp ^2)
use GraphQL\Executor\Promise\Adapter\AmpPromiseAdapter;
$adapter = new AmpPromiseAdapter();

// After (amphp/amp ^3)
use GraphQL\Executor\Promise\Adapter\AmpFutureAdapter;
$adapter = new AmpFutureAdapter();
```

Resolver return types change from `Amp\Promise` to `Amp\Future`:

```php
// Before (amphp/amp ^2)
'resolve' => fn (): \Amp\Promise => \Amp\call(fn (): string => 'value'),

// After (amphp/amp ^3)
'resolve' => fn (): \Amp\Future => \Amp\async(fn (): string => 'value'),
```
