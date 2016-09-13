# Upgrade

## Upgrade v0.6.x > v0.7.x

There are a few new breaking changes in v0.7.0 that were added to the graphql-js reference implementation 
with the spec of April2016

### 1. Context for resolver

You can now pass a custom context to the `GraphQL::execute` function that is available in all resolvers. 
This can for example be used to pass the current user etc. The new signature looks like this:

```php
GraphQL::execute(
    $schema,
    $query,
    $rootObject,
    $context,
    $variables,
    $operationName
);
```

The signature of all resolve methods has changed to the following: 

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

```php
$schema = new Schema([
    'query' => $queryType,
    'mutation' => $mutationType,
    'types' => $arrayOfTypesWithInterfaces // See 3.
]);
```

### 3. Types can be directly passed to schema

In case your implementation creates types on demand, the types might not be available when an interface
is queried and query validation will fail. In that case, you need to pass the types that implement the
interfaces directly to the schema, so it knows of their existence during query validation. 
Also see webonyx/graphql-php#38

If your types are created each time the Schema is created, this can be ignored. 
