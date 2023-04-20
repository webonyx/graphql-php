# Schema Definition

The schema is a container of your type hierarchy, which accepts root types in a constructor and provides
methods for receiving information about your types to internal GraphQL tools.

In **graphql-php**, the schema is an instance of [`GraphQL\Type\Schema`](class-reference.md#graphqltypeschema):

```php
use GraphQL\Type\Schema;

$schema = new Schema([
    'query' => $queryType,
    'mutation' => $mutationType,
]);
```

See possible constructor options [below](#configuration-options).

## Query and Mutation types

The schema consists of two root types:

- **Query** type is a surface of your read API
- **Mutation** type (optional) exposes write API by declaring all possible mutations in your app.

Query and Mutation types are regular [object types](type-definitions/object-types.md) containing root-level fields
of your API:

```php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'hello' => [
            'type' => Type::string(),
            'resolve' => fn () => 'Hello World!',
        ],
        'hero' => [
            'type' => $characterInterface,
            'args' => [
                'episode' => [
                    'type' => $episodeEnum,
                ],
            ],
            'resolve' => fn ($rootValue, array $args): Hero => StarWarsData::getHero($args['episode'] ?? null),
        ]
    ]
]);

$mutationType = new ObjectType([
    'name' => 'Mutation',
    'fields' => [
        'createReview' => [
            'type' => $createReviewOutput,
            'args' => [
                'episode' => Type::nonNull($episodeEnum),
                'review' => Type::nonNull($reviewInputObject),
            ],
            // TODO
            'resolve' => fn ($rootValue, array $args): Review => StarWarsData::createReview($args['episode'], $args['review']),
        ]
    ]
]);
```

Keep in mind that other than the special meaning of declaring a surface area of your API,
those types are the same as any other [object type](type-definitions/object-types.md), and their fields work
exactly the same way.

**Mutation** type is also just a regular object type. The difference is in semantics.
Field names of Mutation type are usually verbs and they almost always have arguments - quite often
with complex input values (see [Mutations and Input Types](type-definitions/inputs.md) for details).

## Configuration Options

The schema constructor expects an instance of [`GraphQL\Type\SchemaConfig`](class-reference.md#graphqltypeschemaconfig)
or an array with the following options:

| Option       | Type                           | Notes                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| ------------ | ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| query        | `ObjectType`                   | **Required.** Object type (usually named "Query") containing root-level fields of your read API                                                                                                                                                                                                                                                                                                                                                               |
| mutation     | `ObjectType`                   | Object type (usually named "Mutation") containing root-level fields of your write API                                                                                                                                                                                                                                                                                                                                                                         |
| subscription | `ObjectType`                   | Reserved for future subscriptions implementation. Currently presented for compatibility with introspection query of **graphql-js**, used by various clients (like Relay or GraphiQL)                                                                                                                                                                                                                                                                          |
| directives   | `array<Directive>`             | A full list of [directives](type-definitions/directives.md) supported by your schema. By default, contains built-in **@skip** and **@include** directives.<br><br> If you pass your own directives and still want to use built-in directives - add them explicitly. For example:<br><br> _array_merge(GraphQL::getStandardDirectives(), [$myCustomDirective]);_                                                                                               |
| types        | `array<ObjectType>`            | List of object types which cannot be detected by **graphql-php** during static schema analysis.<br><br>Most often this happens when the object type is never referenced in fields directly but is still a part of a schema because it implements an interface which resolves to this object type in its **resolveType** callable. <br><br> Note that you are not required to pass all of your types here - it is simply a workaround for a concrete use-case. |
| typeLoader   | `callable(string $name): Type` | Expected to return a type instance given the name. Must always return the same instance if called multiple times, see [lazy loading](#lazy-loading-of-types). See section below on lazy type loading.                                                                                                                                                                                                                                                         |

### Using config class

If you prefer a fluid interface for the config with auto-completion in IDE and static time validation,
use [`GraphQL\Type\SchemaConfig`](class-reference.md#graphqltypeschemaconfig) instead of an array:

```php
use GraphQL\Type\SchemaConfig;
use GraphQL\Type\Schema;

$config = SchemaConfig::create()
    ->setQuery($myQueryType)
    ->setTypeLoader($myTypeLoader);

$schema = new Schema($config);
```

## Lazy loading of types

By default, the schema will scan all of your type, field and argument definitions to serve GraphQL queries.
It may cause performance overhead when there are many types in the schema.

In this case, it is recommended to pass the **typeLoader** option to the schema constructor and define all
of your object **fields** as callbacks.

Type loading is very similar to PHP class loading, but keep in mind that the **typeLoader** must
always return the same instance of a type. A good way to ensure this is to use a type registry:

```php
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class TypeRegistry
{
    /**
     * @var array<string, Type>
     */
    private array $types = [];

    public function get(string $name): Type
    {
        return $this->types[$name] ??= $this->{$name}();
    }

    private function MyTypeA(): ObjectType
    {
        return new ObjectType([
            'name' => 'MyTypeA',
            'fields' => fn() => [
                'b' => [
                    'type' => $this->get('MyTypeB')
                ],
            ]
        ]);
    }

    private function MyTypeB(): ObjectType
    {
        // ...
    }
}

$typeRegistry = new TypeRegistry();

$schema = new Schema([
    'query' => $typeRegistry->get('Query'),
    'typeLoader' => static fn (string $name): Type => $typeRegistry->get($name),
]);
```

You can automate this registry if you wish to reduce boilerplate or even
introduce a Dependency Injection Container if your types have other dependencies.

Alternatively, all methods of the registry could be static - then there is no need
to pass it in the constructor - instead use **TypeRegistry::myAType()** in your type definitions.

## Schema Validation

By default, the schema is created with only shallow validation of type and field definitions  
(because validation requires a full schema scan and is very costly on bigger schemas).

There is a special method **assertValid()** on the schema instance which throws
`GraphQL\Error\InvariantViolation` exception when it encounters any error, like:

- Invalid types used for fields/arguments
- Missing interface implementations
- Invalid interface implementations
- Other schema errors...

Schema validation is supposed to be used in CLI commands or during a build step of your app.
Don't call it in web requests in production.

Usage example:

```php
try {
    $schema = new GraphQL\Type\Schema([
        'query' => $myQueryType
    ]);
    $schema->assertValid();
} catch (GraphQL\Error\InvariantViolation $e) {
    echo $e->getMessage();
}
```
