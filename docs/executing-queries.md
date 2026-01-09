## Using Facade Method

Query execution is a complex process involving multiple steps, including query **parsing**,
**validating** and finally **executing** against your [schema](schema-definition.md).

**graphql-php** provides a convenient facade for this process in class
[`GraphQL\GraphQL`](class-reference.md#graphqlgraphql):

```php
use GraphQL\GraphQL;

$result = GraphQL::executeQuery(
    $schema,
    $queryString,
    $rootValue = null,
    $context = null,
    $variableValues = null,
    $operationName = null,
    $fieldResolver = null,
    $validationRules = null
);
```

It returns an instance of [`GraphQL\Executor\ExecutionResult`](class-reference.md#graphqlexecutorexecutionresult)
which can be easily converted to array:

```php
$serializableResult = $result->toArray();
```

Returned array contains **data** and **errors** keys, as described by the
[GraphQL spec](https://spec.graphql.org/June2018/#sec-Response-Format).
This array is suitable for further serialization (e.g. using **json_encode**).
See also the section on [error handling and formatting](error-handling.md).

### Method arguments

Description of **executeQuery** method arguments:

| Argument        | Type                                                          | Notes                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| --------------- | ------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| schema          | [`GraphQL\Type\Schema`](class-reference.md#graphqltypeschema) | **Required.** Instance of your application [Schema](schema-definition.md)                                                                                                                                                                                                                                                                                                                                                                |
| queryString     | `string` or `GraphQL\Language\AST\DocumentNode`               | **Required.** Actual GraphQL query string to be parsed, validated and executed. If you parse query elsewhere before executing - pass corresponding AST document here to avoid new parsing.                                                                                                                                                                                                                                               |
| rootValue       | `mixed`                                                       | Any value that represents a root of your data graph. It is passed as the 1st argument to field resolvers of [Query type](schema-definition.md#query-and-mutation-types). Can be omitted or set to null if actual root values are fetched by Query type itself.                                                                                                                                                                           |
| context         | `mixed`                                                       | Any value that holds information shared between all field resolvers. Most often they use it to pass currently logged in user, locale details, etc.<br><br>It will be available as the 3rd argument in all field resolvers. (see section on [Field Definitions](type-definitions/object-types.md#field-configuration-options) for reference) **graphql-php** never modifies this value and passes it _as is_ to all underlying resolvers. |
| variableValues  | `array`                                                       | Map of variable values passed along with query string. See section on [query variables on official GraphQL website](https://graphql.org/learn/queries/#variables). Note that while variableValues must be an associative array, the values inside it can be nested using \stdClass if desired.                                                                                                                                           |
| operationName   | `string`                                                      | Allows the caller to specify which operation in queryString will be run, in cases where queryString contains multiple top-level operations.                                                                                                                                                                                                                                                                                              |
| fieldResolver   | `callable`                                                    | A resolver function to use when one is not provided by the schema. If not provided, the [default field resolver is used](data-fetching.md#default-field-resolver).                                                                                                                                                                                                                                                                       |
| validationRules | `array`                                                       | A set of rules for query validation step. The default value is all available rules. Empty array would allow skipping query validation (may be convenient for persisted queries which are validated before persisting and assumed valid during execution)                                                                                                                                                                                 |

## Using Server

If you are building HTTP GraphQL API, you may prefer our Standard Server
(compatible with [express-graphql](https://github.com/graphql/express-graphql)).
It supports more features out of the box, including parsing HTTP requests, producing a spec-compliant response; [batched queries](#query-batching); persisted queries.

Usage example (with plain PHP):

```php
use GraphQL\Server\StandardServer;

$server = new StandardServer([/* server options, see below */]);
$server->handleRequest(); // parses PHP globals and emits response
```

Server also supports [PSR-7 request/response interfaces](https://www.php-fig.org/psr/psr-7/):

```php
use GraphQL\Server\StandardServer;
use GraphQL\Executor\ExecutionResult;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/** @var RequestInterface $psrRequest */
/** @var ResponseInterface $psrResponse */
/** @var StreamInterface $psrBodyStream */
$server = new StandardServer([/* server options, see below */]);
$psrResponse = $server->processPsrRequest($psrRequest, $psrResponse, $psrBodyStream);


// Alternatively create PSR-7 response yourself:

/** @var ExecutionResult|ExecutionResult[] $result */
$result = $server->executePsrRequest($psrRequest);
$jsonResult = json_encode($result, JSON_THROW_ON_ERROR);
$psrResponse = new SomePsr7ResponseImplementation($jsonResult );
```

PSR-7 is useful when you want to integrate the server into existing framework:

- [PSR-7 for Laravel](https://laravel.com/docs/requests#psr7-requests)
- [Symfony PSR-7 Bridge](https://symfony.com/doc/current/components/psr7.html)
- [Slim](https://www.slimframework.com/docs/v4/concepts/value-objects.html)
- [Laminas Mezzio](https://docs.mezzio.dev/mezzio/)

### Server configuration options

| Argument             | Type                                                                        | Notes                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| -------------------- | --------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| schema               | [`Schema`](class-reference.md#graphqltypeschema)                            | **Required.** Instance of your application [Schema](schema-definition.md)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| rootValue            | `mixed`                                                                     | Any value that represents a root of your data graph. It is passed as the 1st argument to field resolvers of [Query type](schema-definition.md#query-and-mutation-types). Can be omitted or set to null if actual root values are fetched by Query type itself.                                                                                                                                                                                                                                                                                                                                                                                  |
| context              | `mixed`                                                                     | Any value that holds information shared between all field resolvers. Most often they use it to pass currently logged in user, locale details, etc.<br><br>It will be available as the 3rd argument in all field resolvers. (see section on [Field Definitions](type-definitions/object-types.md#field-configuration-options) for reference) **graphql-php** never modifies this value and passes it _as is_ to all underlying resolvers.                                                                                                                                                                                                        |
| fieldResolver        | `callable`                                                                  | A resolver function to use when one is not provided by the schema. If not provided, the [default field resolver is used](data-fetching.md#default-field-resolver).                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| validationRules      | `array` or `callable`                                                       | A set of rules for query validation step. The default value is all available rules. The empty array would allow skipping query validation (may be convenient for persisted queries which are validated before persisting and assumed valid during execution).<br><br>Pass `callable` to return different validation rules for different queries (e.g. empty array for persisted query and a full list of rules for regular queries). When passed, it is expected to have the following signature: <br><br> **function ([OperationParams](class-reference.md#graphqlserveroperationparams) $params, DocumentNode $node, $operationType): array** |
| queryBatching        | `bool`                                                                      | Flag indicating whether this server supports query batching ([apollo-style](https://www.apollographql.com/blog/apollo-client/performance/query-batching/)).<br><br> Defaults to **false**                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| debugFlag            | `int`                                                                       | Debug flags. See [docs on error debugging](error-handling.md#debugging-tools) (flag values are the same).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| persistedQueryLoader | `callable`                                                                  | A function which is called to fetch actual query when server encounters a **queryId**.<br><br> The server does not implement persistence part (which you will have to build on your own), but it allows you to execute queries which were persisted previously.<br><br> Expected function signature:<br> **function ($queryId, [OperationParams](class-reference.md#graphqlserveroperationparams) $params)** <br><br>Function is expected to return query **string** or parsed **DocumentNode** <br><br> [Read more about persisted queries](https://www.apollographql.com/blog/apollo-client/persisted-graphql-queries).                       |
| errorFormatter       | `callable`                                                                  | Custom error formatter. See [error handling docs](error-handling.md#custom-error-handling-and-formatting).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| errorsHandler        | `callable`                                                                  | Custom errors handler. See [error handling docs](error-handling.md#custom-error-handling-and-formatting).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| promiseAdapter       | [`PromiseAdapter`](class-reference.md#graphqlexecutorpromisepromiseadapter) | Required for [Async PHP](data-fetching.md#async-php) only.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |

#### Using config class

If you prefer fluid interface for config with autocomplete in IDE and static time validation,
use [`GraphQL\Server\ServerConfig`](class-reference.md#graphqlserverserverconfig) instead of an array:

```php
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;

$config = ServerConfig::create()
    ->setSchema($schema)
    ->setErrorFormatter($myFormatter)
    ->setDebugFlag($debug)
;

$server = new StandardServer($config);
```

## Query batching

Standard Server supports query batching ([apollo-style](https://www.apollographql.com/blog/apollo-client/performance/query-batching/)).

One of the major benefits of Server over a sequence of **executeQuery()** calls is that
[Deferred resolvers](data-fetching.md#solving-n1-problem) won't be isolated in queries.
So for example following batch will require single DB request (if user field is deferred):

```json
[
  {
    "query": "{user(id: 1) { id }}"
  },
  {
    "query": "{user(id: 2) { id }}"
  },
  {
    "query": "{user(id: 3) { id }}"
  }
]
```

To enable query batching, pass **queryBatching** option in server config:

```php
use GraphQL\Server\StandardServer;

$server = new StandardServer([
    'queryBatching' => true
]);
```

## Custom Validation Rules

Before execution, a query is validated using a set of standard rules defined by the GraphQL spec.
It is possible to override standard set of rules globally or per execution.

Add rules globally:

```php
use GraphQL\Validator\Rules;
use GraphQL\Validator\DocumentValidator;

// Add to standard set of rules globally:
DocumentValidator::addRule(new Rules\DisableIntrospection());
```

Custom rules per execution:

```php
use GraphQL\GraphQL;
use GraphQL\Validator\Rules;

$myValidationRules = array_merge(
    GraphQL::getStandardValidationRules(),
    [
        new Rules\QueryComplexity(100),
        new Rules\DisableIntrospection()
    ]
);

$result = GraphQL::executeQuery(
    $schema,
    $queryString,
    $rootValue = null,
    $context = null,
    $variableValues = null,
    $operationName = null,
    $fieldResolver = null,
    $myValidationRules // <-- this will override global validation rules for this request
);
```

Or with a standard server:

```php
use GraphQL\Server\StandardServer;

$server = new StandardServer([
    'validationRules' => $myValidationRules
]);
```

## Validation Caching

Validation is a required step in GraphQL execution, but it can become a performance bottleneck.
In production environments, queries are often static or pre-generated (e.g. persisted queries or queries emitted by client libraries).
This means that many queries will be identical and their validation results can be reused.

To optimize for this, `graphql-php` allows skipping validation for known valid queries.
Leverage pluggable validation caching by passing an implementation of the `GraphQL\Validator\ValidationCache` interface to `GraphQL::executeQuery()`:

```php
use GraphQL\Validator\ValidationCache;
use GraphQL\GraphQL;

$validationCache = new MyPsrValidationCacheAdapter();

$result = GraphQL::executeQuery(
    $schema,
    $queryString,
    $rootValue,
    $context,
    $variableValues,
    $operationName,
    $fieldResolver,
    $validationRules,
    $validationCache
);
```

### Key Generation Tips

You are responsible for generating cache keys that are unique and dependent on the following inputs:

- the client-given query
- the current schema
- the passed validation rules and their implementation
- the implementation of `graphql-php`

Here are some tips:

- Using `serialize()` directly on the schema object may error due to closures or circular references.
  Instead, use `GraphQL\Utils\SchemaPrinter::doPrint($schema)` to get a stable string representation of the schema.
- If using custom validation rules, be sure to account for them in your key (e.g., by serializing or listing their class names and versioning them).
- Include the version number of the `webonyx/graphql-php` package to account for implementation changes in the library.
- Use a stable hash function like `md5()` or `sha256()` to generate the key from the schema, AST, and rules.
- Improve performance even further by hashing inputs known before deploying such as the schema or the installed package version.
  You may store the hash in an environment variable or a constant to avoid recalculating it on every request.
- If you have access to the original query string before parsing, hashing it directly (`hash('sha256', $queryString)`) is more efficient than `serialize($ast)`.
- Frameworks may pass pre-computed schema and query hashes to the cache implementation via the constructor to avoid redundant computation.

### Sample Implementation

```php
use GraphQL\Validator\ValidationCache;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use Psr\SimpleCache\CacheInterface;
use Composer\InstalledVersions;

/**
 * Reference implementation of ValidationCache using PSR-16 cache.
 *
 * @see GraphQl\Tests\PsrValidationCacheAdapter
 */
class MyPsrValidationCacheAdapter implements ValidationCache
{
    private CacheInterface $cache;

    private int $ttlSeconds;

    public function __construct(
        CacheInterface $cache,
        int $ttlSeconds = 300
    ) {
        $this->cache = $cache;
        $this->ttlSeconds = $ttlSeconds;
    }

    public function isValidated(Schema $schema, DocumentNode $ast, ?array $rules = null): bool
    {
        $key = $this->buildKey($schema, $ast);
        return $this->cache->has($key);
    }

    public function markValidated(Schema $schema, DocumentNode $ast, ?array $rules = null): void
    {
        $key = $this->buildKey($schema, $ast);
        $this->cache->set($key, true, $this->ttlSeconds);
    }

    private function buildKey(Schema $schema, DocumentNode $ast, ?array $rules = null): string
    {
        // Include package version to account for implementation changes
        $libraryVersion = \Composer\InstalledVersions::getVersion('webonyx/graphql-php')
            ?? throw new \RuntimeException('webonyx/graphql-php version not found. Ensure the package is installed.');

        // Use a stable hash for the schema
        $schemaHash = md5(SchemaPrinter::doPrint($schema));

        // Serialize AST and rules — both are predictable and safe in this context
        $astHash = md5(serialize($ast));
        $rulesHash = md5(serialize($rules));

        return "graphql_validation_{$libraryVersion}_{$schemaHash}_{$astHash}_{$rulesHash}";
    }
}
```

An optimized version of `buildKey` might leverage a key prefix for inputs known before deployment.
For example, you may run the following once during deployment and save the output in an environment variable `GRAPHQL_VALIDATION_KEY_PREFIX`:

```php
$libraryVersion = \Composer\InstalledVersions::getVersion('webonyx/graphql-php')
    ?? throw new \RuntimeException('webonyx/graphql-php version not found. Ensure the package is installed.');

$schemaHash = md5(SchemaPrinter::doPrint($schema));

echo "{$libraryVersion}_{$schemaHash}";
```

Then use the environment variable in your key generation:

```php
    private function buildKey(Schema $schema, DocumentNode $ast, ?array $rules = null): string
    {
        $keyPrefix = getenv('GRAPHQL_VALIDATION_KEY_PREFIX')
            ?? throw new \RuntimeException('Environment variable GRAPHQL_VALIDATION_KEY_PREFIX is not set.');
        $astHash = md5(serialize($ast));
        $rulesHash = md5(serialize($rules));

        return "graphql_validation_{$keyPrefix}_{$astHash}_{$rulesHash}";
    }
```

### Special Considerations for QueryComplexity

The `QueryComplexity` validation rule requires access to variable values, unlike most validation rules that depend only on the schema and query string.
This means **caching results for `QueryComplexity` is unsafe** — different variable values can produce different complexity scores and different validation outcomes.

If you use `QueryComplexity` with validation caching, consider one of these approaches:

1. **Exclude from caching**: Run `QueryComplexity` separately without caching, then cache all other rules.
2. **Two-phase validation**: First validate and cache "cacheable" rules (everything except `QueryComplexity`), then always run `QueryComplexity` separately.
3. **Skip caching entirely for affected queries**: If the query uses `QueryComplexity`, don't use the cache for that request.

Frameworks like [Lighthouse](https://lighthouse-php.com) implement two-phase validation automatically.
