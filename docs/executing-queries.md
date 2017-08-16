# Overview
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
    $contextValue = null, 
    $variableValues = null, 
    $operationName = null,
    $fieldResolver = null,
    $validationRules = null,
    $promiseAdapter = null
);
```

It returns an instance of [`GraphQL\Executor\ExecutionResult`](/reference/#graphqlexecutorexecutionresult) 
which can be easily converted to array:

```php
$serializableResult = $result->toArray();
```

Returned array contains **data** and **errors** keys, as described by 
[GraphQL spec](http://facebook.github.io/graphql/#sec-Response-Format). 
This array is suitable for further serialization (e.g. using `json_encode`).
See also section on [error handling and formatting](error-handling/).

Description of method arguments:

Argument     | Type     | Notes
------------ | -------- | -----
schema       | `GraphQL\Type\Schema` | **Required.** Instance of your application [Schema](type-system/schema/)
queryString  | `string` or `GraphQL\Language\AST\DocumentNode` | **Required.** Actual GraphQL query string to be parsed, validated and executed. If you parse query elsewhere before executing - pass corresponding ast document here to avoid new parsing.
rootValue  | `mixed` | Any value that represents a root of your data graph. It is passed as 1st argument to field resolvers of [Query type](type-system/schema/#query-and-mutation-types). Can be omitted or set to null if actual root values are fetched by Query type itself.
contextValue  | `mixed` | Any value that holds information shared between all field resolvers. Most often they use it to pass currently logged in user, locale details, etc.<br><br>It will be available as 3rd argument in all field resolvers. (see section on [Field Definitions](type-system/object-types/#field-configuration-options) for reference) **graphql-php** never modifies this value and passes it *as is* to all underlying resolvers.
variableValues | `array` | Map of variable values passed along with query string. See section on [query variables on official GraphQL website](http://graphql.org/learn/queries/#variables)
operationName | `string` | Allows the caller to specify which operation in queryString will be run, in cases where queryString contains multiple top-level operations.
fieldResolver | `callable` | A resolver function to use when one is not provided by the schema. If not provided, the [default field resolver is used](data-fetching/#default-field-resolver).
validationRules | `array` | A set of rules for query validation step. Default value is all available rules. Empty array would allow to skip query validation (may be convenient for persisted queries which are validated before persisting and assumed valid during execution)
promiseAdapter | `GraphQL\Executir\Promise\PromiseAdapter` | Adapter for async-enabled PHP platforms like ReactPHP (read about [async platforms integration](data-fetching/#async-php))

# Execution Result
```php
namespace GraphQL\Executor;

class ExecutionResult 
{
    /**
     * @var array
     */
    public $data;

    /**
     * @var GraphQL\Error\Error[]
     */
    public $errors;

    /**
     *
     */
    public setErrorsHandler(callable $errorsHandler);
    
    public setErrorFormatter(callable $errorsHandler);
}
```


# Parsing
Following reading describes implementation details of query execution process. It may clarify some 
internals of GraphQL but is not required to use it. Feel free to skip to next section 
on [Error Handling](error-handling/) for essentials.

TODOC

# Validating
TODOC

# Executing
TODOC

# Errors explained
There are 3 types of errors in GraphQL:

- **Syntax**: query has invalid syntax and could not be parsed;
- **Validation**: query is incompatible with type system (e.g. unknown field is requested);
- **Execution**: occurs when some field resolver throws (or returns unexpected value).

Obviously when **Syntax** or **Validation** error is detected - process is interrupted and query is not 
executed.

GraphQL is forgiving to **Execution** errors which occur in resolvers of nullable fields. 
If such field throws or returns unexpected value the value of the field in response will be simply 
replaced with `null` and error entry will be registered.

If exception is thrown in non-null field - error bubbles up to first nullable field. This nullable field is  
replaced with `null` and error entry is added to response. If all fields up to the root are non-null - 
**data** entry will be removed from response and only **errors** key will be presented.
