# Defining your schema

[Type language](http://graphql.org/learn/schema/#type-language) is a convenient way to define your schema,
especially with IDE autocompletion and syntax validation.

Here is a simple schema defined in GraphQL type language (e.g. in separate **schema.graphql** file):

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

In order to create schema instance out of this file, use `GraphQL\Utils\BuildSchema`:

```php
<?php
use GraphQL\Utils\BuildSchema;

$contents = file_get_contents('schema.graphql');
$schema = BuildSchema::build($contents);
```

By default such schema is created without any resolvers. As a result it doesn't support **Interfaces** and **Unions**
because it is impossible to resolve actual implementations during execution.

Also we have to rely on [default field resolver](/data-fetching/#default-field-resolver) and **root value** in 
order to execute query against this schema.

# Defining resolvers
In order to enable **Interfaces**, **Unions** and custom field resolvers you can pass second argument: 
**type config decorator** to schema builder. 

It accepts default type config produced by builder and is expected to add missing options like 
[`resolveType`](/type-system/interfaces/#configuration-options) for interface types or 
[`resolveField`](/type-system/object-types/#configuration-options) for object types.

```php
<?php
use GraphQL\Utils\BuildSchema;

$typeConfigDecorator = function($typeConfig, $typeDefinitionNode) {
    $name = $typeConfig['name'];
    // ... add missing options to $typeConfig based on type $name
    return $typeConfig;
};

$contents = file_get_contents('schema.graphql');
$schema = BuildSchema::build($contents, $typeConfigDecorator);
```

# Performance considerations
Method `BuildSchema::build()` produces a [lazy schema](/type-system/schema/#lazy-loading-of-types)
automatically, so it works efficiently even with very large schemas.

But parsing type definition file on each request is suboptimal, so it is recommended to cache 
intermediate parsed representation of the schema for production environment:

```php
<?php
use GraphQL\Language\Parser;
use GraphQL\Utils\BuildSchema;
use GraphQL\Language\AST\Node;

$cacheFilename = 'cached_schema.php';

if (!file_exists($cacheFilename)) {
    $document = Parser::parse(file_get_contents('./schema.graphql'));
    file_put_contents($cacheFilename, "<?php\nreturn " . var_export($document->toArray(), true));
} else {
    $document = Node::fromArray(require $cacheFilename); // fromArray() is a lazy operation as well
}

$typeConfigDecorator = function () {};
$schema = BuildSchema::build($document, $typeConfigDecorator);
```