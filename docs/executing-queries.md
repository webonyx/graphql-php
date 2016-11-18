# Overview
Query execution is a complex process involving multiple steps, including query **parsing**, 
**validating** and finally **executing** against your [schema](type-system/schema/).

**graphql-php** provides convenient facade for this process in class `GraphQL\GraphQL`:

```php
use GraphQL\GraphQL;

$result = GraphQL::execute(
    $schema, 
    $queryString, 
    $rootValue = null, 
    $contextValue = null, 
    $variableValues = null, 
    $operationName = null
);
```

Method returns `array` with **data** and **errors** keys, as described by 
[GraphQL specs](http://facebook.github.io/graphql/#sec-Response-Format).
This array is suitable for further serialization (e.g. using `json_encode`). 
See also section on [error handling](error-handling/).


Description of method arguments:

Argument     | Type     | Notes
------------ | -------- | -----
schema       | `GraphQL\Schema` | **Required.** Instance of your application [Schema](type-system/schema/)
queryString  | `string` or `GraphQL\Language\AST\DocumentNode` | **Required.** Actual GraphQL query string to be parsed, validated and executed. If you parse query elsewhere before executing - pass corresponding ast document here to avoid new parsing.
rootValue  | `mixed` | Any value that represents a root of your data graph. It is passed as 1st argument to field resolvers of [Query type](type-system/schema/#query-and-mutation-types). Can be omitted or set to null if actual root values are fetched by Query type itself.
contextValue  | `mixed` | Any value that holds information shared between all field resolvers. Most often they use it to pass currently logged in user, locale details, etc.<br><br>It will be available as 3rd argument in all field resolvers. (see section on [Field Definitions](type-system/object-types/#field-configuration-options) for reference) **graphql-php** never modifies this value and passes it *as is* to all underlying resolvers.
variableValues | `array` | Map of variable values passed along with query string. See section on [query variables on official GraphQL website](http://graphql.org/learn/queries/#variables)
operationName | `string` | Allows the caller to specify which operation in queryString will be run, in cases where queryString contains multiple top-level operations.

# Parsing
Following reading describes implementation details of query execution process. It may clarify some 
internals of GraphQL but is not required in order to use it. Feel free to skip to next section 
on [Error Handling](error-handling/) for essentials.

TODOC

# Validating
TODOC

# Executing
TODOC
