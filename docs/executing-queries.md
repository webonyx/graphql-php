# Using Facade Method
Query execution is a complex process involving multiple steps, including query **parsing**, 
**validating** and finally **executing** against your [schema](type-system/schema/).

**graphql-php** provides convenient facade for this process in class 
[`GraphQL\GraphQL`](/reference/#graphqlgraphql):

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

It returns an instance of [`GraphQL\Executor\ExecutionResult`](/reference/#graphqlexecutorexecutionresult) 
which can be easily converted to array:

```php
$serializableResult = $result->toArray();
```

Returned array contains **data** and **errors** keys, as described by 
[GraphQL spec](http://facebook.github.io/graphql/#sec-Response-Format). 
This array is suitable for further serialization (e.g. using **json_encode**).
See also section on [error handling and formatting](error-handling/).

Description of **executeQuery** method arguments:

Argument     | Type     | Notes
------------ | -------- | -----
schema       | [`GraphQL\Type\Schema`](#) | **Required.** Instance of your application [Schema](type-system/schema/)
queryString  | `string` or `GraphQL\Language\AST\DocumentNode` | **Required.** Actual GraphQL query string to be parsed, validated and executed. If you parse query elsewhere before executing - pass corresponding ast document here to avoid new parsing.
rootValue  | `mixed` | Any value that represents a root of your data graph. It is passed as 1st argument to field resolvers of [Query type](type-system/schema/#query-and-mutation-types). Can be omitted or set to null if actual root values are fetched by Query type itself.
context  | `mixed` | Any value that holds information shared between all field resolvers. Most often they use it to pass currently logged in user, locale details, etc.<br><br>It will be available as 3rd argument in all field resolvers. (see section on [Field Definitions](type-system/object-types/#field-configuration-options) for reference) **graphql-php** never modifies this value and passes it *as is* to all underlying resolvers.
variableValues | `array` | Map of variable values passed along with query string. See section on [query variables on official GraphQL website](http://graphql.org/learn/queries/#variables)
operationName | `string` | Allows the caller to specify which operation in queryString will be run, in cases where queryString contains multiple top-level operations.
fieldResolver | `callable` | A resolver function to use when one is not provided by the schema. If not provided, the [default field resolver is used](data-fetching/#default-field-resolver).
validationRules | `array` | A set of rules for query validation step. Default value is all available rules. Empty array would allow to skip query validation (may be convenient for persisted queries which are validated before persisting and assumed valid during execution)

# Using Server
If you are building HTTP GraphQL API, you may prefer our Standard Server 
(compatible with [express-graphql](https://github.com/graphql/express-graphql)). 
It supports more features out of the box, including parsing HTTP requests, producing spec-compliant response; [batched queries](#query-batching); persisted queries.

Usage example (with plain PHP):

```php
<?php
use GraphQL\Server\StandardServer;

$server = new StandardServer([/* server options, see below */]);
$server->handleRequest(); // parses PHP globals and emits response
```

Server also supports [PSR-7 request/response interfaces](http://www.php-fig.org/psr/psr-7/):
```php
<?php
use GraphQL\Server\StandardServer;
use GraphQL\Executor\ExecutionResult;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/** @var ServerRequestInterface $psrRequest */
/** @var ResponseInterface $psrResponse */
/** @var StreamInterface $psrBodyStream */
$server = new StandardServer([/* server options, see below */]);
$psrResponse = $server->processPsrRequest($psrRequest, $psrResponse, $psrBodyStream);


// Alternatively create PSR-7 response yourself:

/** @var ExecutionResult|ExecutionResult[] $result */
$result = $server->executePsrRequest($psrRequest);
$psrResponse = new SomePsr7ResponseImplementation(json_encode($result));
```

PSR-7 is useful when you want to integrate the server into existing framework:

- [PSR-7 for Laravel](https://laravel.com/docs/5.1/requests#psr7-requests)
- [Symfony PSR-7 Bridge](https://symfony.com/doc/current/request/psr7.html)
- [Slim](https://www.slimframework.com/docs/concepts/value-objects.html)
- [Zend Diactoros](https://zendframework.github.io/zend-diactoros/)

## Server configuration options

Argument     | Type     | Notes
------------ | -------- | -----
schema       | [`Schema`](reference/#graphqltypeschema) | **Required.** Instance of your application [Schema](type-system/schema/)
rootValue  | `mixed` | Any value that represents a root of your data graph. It is passed as 1st argument to field resolvers of [Query type](type-system/schema/#query-and-mutation-types). Can be omitted or set to null if actual root values are fetched by Query type itself.
context  | `mixed` | Any value that holds information shared between all field resolvers. Most often they use it to pass currently logged in user, locale details, etc.<br><br>It will be available as 3rd argument in all field resolvers. (see section on [Field Definitions](type-system/object-types/#field-configuration-options) for reference) **graphql-php** never modifies this value and passes it *as is* to all underlying resolvers.
fieldResolver | `callable` | A resolver function to use when one is not provided by the schema. If not provided, the [default field resolver is used](data-fetching/#default-field-resolver).
validationRules | `array` or `callable` | A set of rules for query validation step. Default value is all available rules. Empty array would allow to skip query validation (may be convenient for persisted queries which are validated before persisting and assumed valid during execution).<br><br>Pass `callable` to return different validation rules for different queries (e.g. empty array for persisted query and full list of rules for regular queries). When passed, it is expected to have following signature: <br><br> **function (OperationParams $params, DocumentNode $node, $operationType): array** <br><br> See also docs on [OperationParams](reference/#graphqlserveroperationparams).
queryBatching | `bool` | Flag indicating whether this server supports query batching ([apollo-style](https://dev-blog.apollodata.com/query-batching-in-apollo-63acfd859862)).<br><br> Defaults to **false**
debug | `int` | Debug flags. See [docs on error debugging](error-handling/#debugging-tools) (flag values are the same).
persistentQueryLoader | `callable` | Function which is called to fetch actual query when server encounters **queryId** in request vs **query**.<br><br> Server does not implement persistence part (which you will have to build on your own), but it allows you to execute queries which were persisted previously.<br><br> Expected function signature:<br> **function ($queryId, OperationParams $params)** <br><br>Function is expected to return query **string** or parsed **DocumentNode** <br><br> See also docs on [OperationParams](reference/#graphqlserveroperationparams). <br><br> [Read more about persisted queries](https://dev-blog.apollodata.com/persisted-graphql-queries-with-apollo-client-119fd7e6bba5).
errorFormatter | `callable` | Custom error formatter. See [error handling docs](error-handling/#custom-error-handling-and-formatting).
errorsHandler | `callable` | Custom errors handler. See [error handling docs](error-handling/#custom-error-handling-and-formatting).
promiseAdapter | [`PromiseAdapter`](reference/#graphqlexecutorpromisepromiseadapter) | Required for [Async PHP](data-fetching/#async-php) only. 

**Server config instance**

If you prefer fluid interface for config with autocomplete in IDE and static time validation, 
use `GraphQL\Server\ServerConfig` instead of an array:

```php
<?php
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;

$config = ServerConfig::create()
    ->setSchema($schema)
    ->setErrorFormatter($myFormatter)
    ->setDebug($debug)
;

$server = new StandardServer($config);
```

## Query batching
Standard Server supports query batching ([apollo-style](https://dev-blog.apollodata.com/query-batching-in-apollo-63acfd859862)).

One of the major benefits of Server over sequence of **executeQuery()** calls is that 
[Deferred resolvers](data-fetching/#solving-n1-problem) won't be isolated in queries.

So for example following batch will require single DB request (if user field is deferred):

```json
[
  {
    "query": "{user(id: 1)} { id }"
  },
  {
    "query": "{user(id: 2)} { id }"
  },
  {
    "query": "{user(id: 3)} { id }"
  }
]
```
