# Upgrade

## Upgrade v0.7.x > v0.8.x
All of those changes apply to those who extends various parts of this library.
If you only use the library and don't try to extend it - everything should work without breaks.


### Breaking: Custom directives handling
When passing custom directives to schema, default directives (like `@skip` and `@include`) 
are not added to schema automatically anymore. If you need them - add them explicitly with 
your other directives.

Before the change:
```php
$schema = new Schema([
   // ...
   'directives' => [$myDirective]
]);
```

After the change:
```php
$schema = new Schema([
    // ...
    'directives' => array_merge(GraphQL::getInternalDirectives(), [$myDirective])
]);
```

### Breaking: Schema protected property and methods visibility 
Most of the `protected` properties and methods of `GraphQL\Schema` were changed to `private`.
Please use public interface instead.

### Breaking: Node kind constants
Node kind constants were extracted from `GraphQL\Language\AST\Node` to 
separate class `GraphQL\Language\AST\NodeKind`

### Non-breaking: AST node classes renamed
AST node classes were renamed to disambiguate with types. e.g.:

```
GraphQL\Language\AST\Field -> GraphQL\Language\AST\FieldNode
GraphQL\Language\AST\OjbectValue -> GraphQL\Language\AST\OjbectValueNode
```
etc. 

Old names are still available via `class_alias` defined in `src/deprecated.php`. 
This file is included automatically when using composer autoloading.

### Deprecations
There are several deprecations which still work, but trigger `E_USER_DEPRECATED` when used.

For example `GraphQL\Executor\Executor::setDefaultResolveFn()` is renamed to `setDefaultResolver()`
but still works with old name.

## Upgrade v0.6.x > v0.7.x

There are a few new breaking changes in v0.7.0 that were added to the graphql-js reference implementation 
with the spec of April2016

### 1. Context for resolver

You can now pass a custom context to the `GraphQL::execute` function that is available in all resolvers as 3rd argument. 
This can for example be used to pass the current user etc.

Make sure to update all calls to `GraphQL::execute`, `GraphQL::executeAndReturnResult`, `Executor::execute` and all 
`'resolve'` callbacks in your app.
 
Before v0.7.0 `GraphQL::execute` signature looked this way:
 ```php
 GraphQL::execute(
     $schema,
     $query,
     $rootValue,
     $variables,
     $operationName
 );
 ```

Starting from v0.7.0 the signature looks this way (note the new `$context` argument):
```php
GraphQL::execute(
    $schema,
    $query,
    $rootValue,
    $context,
    $variables,
    $operationName
);
```

Before v.0.7.0 resolve callbacks had following signature:
```php
/**
 * @param mixed $object The parent resolved object
 * @param array $args Input arguments
 * @param ResolveInfo $info ResolveInfo object
 * @return mixed
 */
function resolveMyField($object, array $args, ResolveInfo $info) {
    //...
}
```

Starting from v0.7.0 the signature has changed to (note the new `$context` argument): 
```php
/**
 * @param mixed $object The parent resolved object
 * @param array $args Input arguments
 * @param mixed $context The context object hat was passed to GraphQL::execute
 * @param ResolveInfo $info ResolveInfo object
 * @return mixed
 */
function resolveMyField($object, array $args, $context, ResolveInfo $info){
    //...
}
```

### 2. Schema constructor signature

The signature of the Schema constructor now accepts an associative config array instead of positional arguments:
 
Before v0.7.0:
```php
$schema = new Schema($queryType, $mutationType);
```

Starting from v0.7.0:
```php
$schema = new Schema([
    'query' => $queryType,
    'mutation' => $mutationType,
    'types' => $arrayOfTypesWithInterfaces // See 3.
]);
```

### 3. Types can be directly passed to schema

There are edge cases when GraphQL cannot infer some types from your schema. 
One example is when you define a field of interface type and object types implementing 
this interface are not referenced anywhere else.

In such case object types might not be available when an interface is queried and query 
validation will fail. In that case, you need to pass the types that implement the
interfaces directly to the schema, so that GraphQL knows of their existence during query validation.

For example:
```php
$schema = new Schema([
    'query' => $queryType,
    'mutation' => $mutationType,
    'types' => $arrayOfTypesWithInterfaces
]);
```

Note that you don't need to pass all types here - only those types that GraphQL "doesn't see" 
automatically. Before v7.0.0 the workaround for this was to create a dumb (non-used) field per 
each "invisible" object type.

Also see [webonyx/graphql-php#38](https://github.com/webonyx/graphql-php/issues/38)
