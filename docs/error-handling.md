## Errors in GraphQL

There are 3 types of errors in GraphQL:

- **Syntax**: query has invalid syntax and could not be parsed;
- **Validation**: query is incompatible with type system (e.g. unknown field is requested);
- **Execution**: occurs when some field resolver throws (or returns unexpected value).

When **Syntax** or **Validation** errors are detected, an exception is thrown
and the query is not executed.

Exceptions thrown during query execution are caught and collected in the result.
They are available in **$errors** prop of [`GraphQL\Executor\ExecutionResult`](class-reference.md#graphqlexecutorexecutionresult).

GraphQL is forgiving to **Execution** errors which occur in resolvers of nullable fields.
If such field throws or returns unexpected value the value of the field in response will be simply
replaced with **null** and error entry will be registered.

If an exception is thrown in the non-null field - error bubbles up to the first nullable field.
This nullable field is replaced with **null** and error entry is added to the result.
If all fields up to the root are non-null - **data** entry will be removed from the result  
and only **errors** key will be presented.

When the result is converted to a serializable array using its **toArray()** method, all errors are
converted to arrays as well using default error formatting (see below).

Alternatively, you can apply [custom error filtering and formatting](#custom-error-handling-and-formatting)
for your specific requirements.

## Default Error formatting

By default, each error entry is converted to an associative array with following structure:

```php
[
    'message' => 'Error message',
    'extensions' => [
        'key' => 'value',
    ],
    'locations' => [
        ['line' => 1, 'column' => 2],
    ],
    'path' => [
        'listField',
        0,
        'fieldWithException',
    ],
];
```

Entry at key **locations** points to a character in query string which caused the error.
In some cases (like deep fragment fields) locations will include several entries to track down
the path to field with the error in query.

Entry at key **path** exists only for errors caused by exceptions thrown in resolvers.
It contains a path from the very root field to actual field value producing an error
(including indexes for list types and field names for composite types).

### Internal errors

As of version **0.10.0**, all exceptions thrown in resolvers are reported with generic message **"Internal server error"**.
This is done to avoid information leak in production environments (e.g. database connection errors, file access errors, etc).

Only exceptions implementing interface [`GraphQL\Error\ClientAware`](class-reference.md#graphqlerrorclientaware) and claiming themselves as **safe** will
be reported with a full error message.

For example:

```php
use GraphQL\Error\ClientAware;

class MySafeException extends \Exception implements ClientAware
{
    public function isClientSafe(): bool
    {
        return true;
    }
}
```

When such exception is thrown it will be reported with a full error message:

```php
[
    'message' => 'My reported error',
    'locations' => [
        ['line' => 10, 'column' => 2],
    ],
    'path' => [
        'path',
        'to',
        'fieldWithException',
    ]
];
```

To change default **"Internal server error"** message to something else, use:

```php
GraphQL\Error\FormattedError::setInternalErrorMessage("Unexpected error");
```

## Debugging tools

During development or debugging, use `DebugFlag::INCLUDE_DEBUG_MESSAGE` to
add hidden error messages each formatted error entry under the key `extensions.debugMessage`.

```php
use GraphQL\GraphQL;
use GraphQL\Error\DebugFlag;

$result = GraphQL::executeQuery(/*args*/)
    ->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);
```

If you also want to add the exception trace, pass `DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE` instead.
This will make each error entry look like this:

```php
[
    'message' => 'Internal server error',
    'locations' => [
        ['line' => 10, 'column' => 2],
    ],
    'path' => [
        'listField',
        0,
        'fieldWithException',
    ],
    'extensions' => [
        'debugMessage' => 'Actual exception message',
        'trace' => [
            /* Formatted original exception trace */
        ],
    ],
]
```

If you prefer the first resolver exception to be re-thrown, use the following flags:

```php
use GraphQL\GraphQL;
use GraphQL\Error\DebugFlag;

$executionResult = GraphQL::executeQuery(/*args*/);

// Will throw if there was an exception in resolver during execution
$executionResult ->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::RETHROW_INTERNAL_EXCEPTIONS);
```

If you only want to re-throw Exceptions that are not marked as safe through the `ClientAware` interface, use `DebugFlag::RETHROW_UNSAFE_EXCEPTIONS`.

## Custom Error Handling and Formatting

It is possible to define custom **formatter** and **handler** for result errors.

**Formatter** is responsible for converting instances of [`GraphQL\Error\Error`](class-reference.md#graphqlerrorerror)
to an array. **Handler** is useful for error filtering and logging.

For example, these are default formatter and handler:

```php
use GraphQL\GraphQL;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;

$result = GraphQL::executeQuery(/* $args */)
    ->setErrorFormatter(fn (Error $error): array => FormattedError::createFromException($error))
    ->setErrorsHandler(fn (array $errors, callable $formatter): array => array_map($formatter, $errors))
    ->toArray();
```

Note that when you pass [debug flags](#debugging-tools) to **toArray()** your custom formatter will still be
decorated with same debugging information mentioned above.

## Schema Errors

So far we only covered errors which occur during query execution process. Schema definition can
also throw `GraphQL\Error\InvariantViolation` if there is an error in one of type definitions.

Usually such errors mean that there is some logical error in your schema.
In this case it makes sense to return a status code `500 (Internal Server Error)` for GraphQL endpoint:

```php
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Error\FormattedError;

try {
    $schema = new Schema([
        // ...
    ]);

    $body = GraphQL::executeQuery($schema, $query);
    $status = 200;
} catch(\Exception $e) {
    $body = [
        'errors' => [FormattedError::createFromException($e)]
    ];
    $status = 500;
}

header('Content-Type: application/json', true, $status);
echo json_encode($body, JSON_THROW_ON_ERROR);
```
