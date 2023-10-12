# Schema Definition Language

Since 0.9.0

The [schema definition language](https://graphql.org/learn/schema/#type-language) is a convenient way to define your schema,
especially with IDE autocompletion and syntax validation.

You can define this separate from your PHP code, e.g. in a **schema.graphql** file:

```graphql
schema {
  query: Query
  mutation: Mutation
}

type Query {
  greetings(input: HelloInput!): String!
}

input HelloInput {
  firstName: String!
  lastName: String
}
```

In order to create schema instance out of this file, use
[`GraphQL\Utils\BuildSchema`](class-reference.md#graphqlutilsbuildschema):

```php
use GraphQL\Utils\BuildSchema;

$contents = file_get_contents('schema.graphql');
$schema = BuildSchema::build($contents);
```

By default, such schema is created without any resolvers.

We have to rely on [default field resolver](data-fetching.md#default-field-resolver) and **root value** in
order to execute a query against this schema.

## Defining resolvers

Since 0.10.0

In order to enable **Interfaces**, **Unions** and custom field resolvers you can pass the second argument:
**type config decorator** to schema builder.

It accepts default type config produced by the builder and is expected to add missing options like
[**resolveType**](type-definitions/interfaces.md#configuration-options) for interface types or
[**resolveField**](type-definitions/object-types.md#configuration-options) for object types.

```php
use GraphQL\Utils\BuildSchema;
use GraphQL\Language\AST\TypeDefinitionNode;

$typeConfigDecorator = function (array $typeConfig, TypeDefinitionNode $typeDefinitionNode): array {
    $name = $typeConfig['name'];
    // ... add missing options to $typeConfig based on type $name
    return $typeConfig;
};

$contents = file_get_contents('schema.graphql');
$schema = BuildSchema::build($contents, $typeConfigDecorator);
```

## Performance considerations

Since 0.10.0

Method **build()** produces a [lazy schema](schema-definition.md#lazy-loading-of-types)
automatically, so it works efficiently even with very large schemas.

But parsing type definition file on each request is suboptimal, so it is recommended to cache
intermediate parsed representation of the schema for the production environment:

```php
use GraphQL\Language\Parser;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\AST;

$cacheFilename = 'cached_schema.php';

if (!file_exists($cacheFilename)) {
    $document = Parser::parse(file_get_contents('./schema.graphql'));
    file_put_contents($cacheFilename, "<?php\nreturn " . var_export(AST::toArray($document), true) . ";\n");
} else {
    $document = AST::fromArray(require $cacheFilename); // fromArray() is a lazy operation as well
}

$typeConfigDecorator = function () {};
$schema = BuildSchema::build($document, $typeConfigDecorator);
```
