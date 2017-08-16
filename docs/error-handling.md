# Errors in GraphQL

Query execution process never throws exceptions. Instead all errors are caught and collected in 
[execution result](executing-queries/#execution-result).

Later `$result->toArray()` automatically converts these errors to array using default 
error formatting. But you can apply [custom error filtering and formatting](#custom-error-filtering-and-formatting)
for your specific requirements.

# Default Error formatting
By default each error entry is converted to associative array with following structure:

```php
[
    'message' => 'Error message',
    'category' => 'graphql',
    'locations' => [
        ['line' => 1, 'column' => 2]
    ],
    'path': [
        'listField',
        0,
        'fieldWithException'
    ]
]
```
Entry at key **locations** points to character in query string which caused the error.
In some cases (like deep fragment fields) locations will include several entries to track down path to 
field with error in query.

Entry at key **path** exists only for errors caused by exceptions thrown in resolvers. It contains path 
from the very root field to actual field value producing an error 
(including indexes for list types and field names for composite types). 

**Internal errors**

As of version **0.10.0** all exceptions thrown in resolvers are reported with generic message **"Internal server error"**.
This is done to avoid information leak in production environments (e.g. database connection errors, file access errors, etc).

Only exceptions implementing interface `GraphQL\Error\ClientAware` and claiming themselves as **safe** will 
be reported with full error message.

For example:
```php
use GraphQL\Error\ClientAware;

class MySafeException extends \Exception implements ClientAware
{
    public function isClientSafe()
    {
        return true;
    }
    
    public function getCategory()
    {
        return 'businessLogic';
    }
}
```
When such exception is thrown it will be reported with full error message:
```php
[
    'message' => 'My reported error',
    'category' => 'businessLogic',
    'locations' => [
        ['line' => 10, 'column' => 2]
    ],
    'path': [
        'path',
        'to',
        'fieldWithException'
    ]
]
```

To change default **"Internal server error"** message to something else, use: 
```
GraphQL\Error\FormattedError::setInternalErrorMessage("Unexpected error");
```

#Debugging tools

During development or debugging use `$result->toArray(true)` to add **debugMessage** key to 
each formatted error entry. If you also want to add exception trace - pass flags instead:

```
use GraphQL\Error\FormattedError;
$debug = FormattedError::INCLUDE_DEBUG_MESSAGE | FormattedError::INCLUDE_TRACE;
$result = GraphQL::executeQuery(/*args*/)->toArray($debug);
```

This will make each error entry to look like this:
```php
[
    'message' => 'Internal server error',
    'debugMessage' => 'Actual exception message',
    'category' => 'internal',
    'locations' => [
        ['line' => 10, 'column' => 2]
    ],
    'path': [
        'listField',
        0,
        'fieldWithException'
    ],
    'trace' => [
        /* Formatted original exception trace */
    ]
]
```

If you prefer first resolver exception to be re-thrown, use following flags:
```php
use GraphQL\Error\FormattedError;
$debug = FormattedError::INCLUDE_DEBUG_MESSAGE | FormattedError::RETHROW_RESOLVER_EXCEPTIONS;

// Following will throw if there was an exception in resolver during execution:
$result = GraphQL::executeQuery(/*args*/)->toArray($debug); 
```

# Custom Error Handling and Formatting
It is possible to define custom **formatter** and **handler** for result errors.

**Formatter** is responsible for converting instances of `GraphQL\Error\Error` to array.
**Handler** is useful for error filtering and logging. 

For example these are default formatter and handler:

```php
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;

$myErrorFormatter = function(Error $error) {
    return FormattedError::createFromException($error);
};

$myErrorHandler = function(array $errors, callable $formatter) {
    return array_map($formatter, $errors);
};

$result = GraphQL::executeQuery(/* $args */)
    ->setErrorFormatter($myErrorFormatter)
    ->setErrorHandler($myErrorHandler)
    ->toArray();
```

You may also re-throw exceptions in result handler for debugging, etc.

# Schema Errors
So far we only covered errors which occur during query execution process. But schema definition can 
also throw `GraphQL\Error\InvariantViolation` if there is an error in one of type definitions.

Usually such errors mean that there is some logical error in your schema and it is the only case 
when it makes sense to return `500` error code for GraphQL endpoint:

```php
try {
    $schema = new Schema([
        // ...
    ]);
    
    $body = GraphQL::executeQuery($schema, $query);
    $status = 200;
} catch(\Exception $e) {
    $body = json_encode([
        'message' => 'Unexpected error'
    ]);
    $status = 500;
}

header('Content-Type: application/json', true, $status);
echo json_encode($body);
```
