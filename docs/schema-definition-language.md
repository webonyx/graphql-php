# Schema Definition Language

The [schema definition language](https://graphql.org/learn/schema/#type-language) is a convenient way to define your schema,
especially with IDE autocompletion and syntax validation.

You can define this separately from your PHP code.
An example for a **schema.graphql** file might look like this:

```graphql
type Query {
  greetings(input: HelloInput!): String!
}

input HelloInput {
  firstName: String!
  lastName: String
}
```

To create an executable schema instance from this file, use [`GraphQL\Utils\BuildSchema`](class-reference.md#graphqlutilsbuildschema):

```php
use GraphQL\Utils\BuildSchema;

$contents = file_get_contents('schema.graphql');
$schema = BuildSchema::build($contents);
```

By default, such a schema is created without any resolvers.
We have to rely on [the default field resolver](data-fetching.md#default-field-resolver)
and the **root value** to execute queries against this schema.

## Defining resolvers

To enable **Interfaces**, **Unions**, and custom field resolvers,
you can pass the second argument **callable $typeConfigDecorator** to **BuildSchema::build()**.

It accepts a callable that receives the default type config produced by the builder and is expected to add missing options like
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

You can learn more about using `$typeConfigDecorator` in [examples/05-type-config-decorator](https://github.com/webonyx/graphql-php/blob/master/examples/05-type-config-decorator).

## Performance considerations

Method **BuildSchema::build()** produces a [lazy schema](schema-definition.md#lazy-loading-of-types) automatically,
so it works efficiently even with huge schemas.

However, parsing the schema definition file on each request is suboptimal.
It is recommended to cache the intermediate parsed representation of the schema for the production environment:

```php
use GraphQL\Language\Parser;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\AST;

$cacheFilename = 'cached_schema.php';

if (!file_exists($cacheFilename)) {
    $document = Parser::parse(file_get_contents('./schema.graphql'));
    DocumentValidator::assertValidSDL($document);
    file_put_contents($cacheFilename, "<?php\nreturn " . var_export(AST::toArray($document), true) . ";\n");
} else {
    $document = AST::fromArray(require $cacheFilename); // fromArray() is a lazy operation as well
}

$typeConfigDecorator = function () {};
$schema = BuildSchema::build($document, $typeConfigDecorator, ['assumeValidSDL' => true]);
```
