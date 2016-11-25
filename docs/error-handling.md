# Errors in GraphQL

Query execution process never throws exceptions. Instead all errors that occur during query execution
are caught, collected and included in response. 

There are 3 types of errors in GraphQL (Syntax, Validation and Execution errors):

**Syntax** errors are returned in response when query has invalid syntax and could not be parsed.
Example output for invalid query `{hello` (missing bracket):
```php
[
    'errors' => [
        [
            'message' => "Syntax Error GraphQL request (1:7) Expected Name, found <EOF>\n\n1: {hello\n         ^\n",
            'locations' => [
                ['line' => 1, 'column' => 7]
            ]
        ]
    ]
]
```

**Validation** errors - returned in response when query has semantic errors. 
Example output for invalid query `{unknownField}`:
```php
[
    'errors' => [
        [
            'message' => 'Cannot query field "unknownField" on type "Query".',
            'locations' => [
                ['line' => 1, 'column' => 2]
            ]
        ]
    ]
]
```

**Execution** errors - included in response when some field resolver throws 
(or returns unexpected value). Example output for query with exception thrown in 
field resolver `{fieldWithException}`:
```php
[
    'data' => [
        'fieldWithException' => null
    ],
    'errors' => [
        [
            'message' => 'Exception message thrown in field resolver',
            'locations' => [
                ['line' => 1, 'column' => 2]
            ],
            'path': [
                'fieldWithException'
            ]
        ]
    ]
]
```

Obviously when **Syntax** or **Validation** error is detected - process is interrupted and query is not 
executed. In such scenarios response only contains **errors**, but not **data**.

GraphQL is forgiving to **Execution** errors which occur in resolvers of nullable fields. 
If such field throws or returns unexpected value the value of the field in response will be simply 
replaced with `null` and error entry will be added to response.

If exception is thrown in non-null field - it will be bubbled up to first nullable field which will
be replaced with `null` (and error entry added to response). If all fields up to the root are non-null - 
**data** entry will be removed from n response and only **errors** key will be presented.

# Debugging tools

Each error entry contains pointer to line and column in original query string which caused 
the error:
 
```php
'locations' => [
    ['line' => 1, 'column' => 2]
]
```
 
 GraphQL clients like **Relay** or **GraphiQL** leverage this information to highlight 
actual piece of query containing error. 

In some cases (like deep fragment fields) locations will include several entries to track down the 
path to field with error in query.

**Execution** errors also contain **path** from the very root field to actual field value producing 
an error (including indexes for array types and fieldNames for object types). So in complex situation 
this path could look like this:

```php
'path' => [
    'lastStoryPosted',
    'author',
    'friends',
    3
    'fieldWithException'
]
```

# Custom Error Formatting

If you want to apply custom formatting to errors - use **GraphQL::executeAndReturnResult()** instead
of **GraphQL::execute()**.

It has exactly the same [signature](executing-queries/), but instead of array it 
returns `GraphQL\Executor\ExecutionResult` instance which holds errors in public **$errors** 
property and data in **$data** property.

Each entry of **$errors** array contains instance of `GraphQL\Error\Error` which wraps original 
exceptions thrown by resolvers. To access original exceptions use `$error->getPrevious()` method.
But note that previous exception is only available for **Execution** errors and will be `null`
for **Syntax** or **Validation** errors.

# Schema Errors
So far we only covered errors which occur during query execution process. But schema definition can 
also throw if there is an error in one of type definitions.

Usually such errors mean that there is some logical error in your schema and it is the only case 
when it makes sense to return `500` error code for GraphQL endpoint:

```php
try {
    $schema = new Schema([
        // ...
    ]);
    
    $body = GraphQL::execute($schema, $query);
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
