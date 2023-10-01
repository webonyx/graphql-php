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

| Option       | Type                                                | Notes                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| ------------ | --------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| query        | `ObjectType` or `callable(): ?ObjectType` or `null` | **Required.** Object type (usually named `Query`) containing root-level fields of your read API                                                                                                                                                                                                                                                                                                                                                               |
| mutation     | `ObjectType` or `callable(): ?ObjectType` or `null` | Object type (usually named `Mutation`) containing root-level fields of your write API                                                                                                                                                                                                                                                                                                                                                                         |
| subscription | `ObjectType` or `callable(): ?ObjectType` or `null` | Reserved for future subscriptions implementation. Currently presented for compatibility with introspection query of **graphql-js**, used by various clients (like Relay or GraphiQL)                                                                                                                                                                                                                                                                          |
| directives   | `array<Directive>`                                  | A full list of [directives](type-definitions/directives.md) supported by your schema. By default, contains built-in **@skip** and **@include** directives.<br><br> If you pass your own directives and still want to use built-in directives - add them explicitly. For example:<br><br> _array_merge(GraphQL::getStandardDirectives(), [$myCustomDirective]);_                                                                                               |
| types        | `array<ObjectType>`                                 | List of object types which cannot be detected by **graphql-php** during static schema analysis.<br><br>Most often this happens when the object type is never referenced in fields directly but is still a part of a schema because it implements an interface which resolves to this object type in its **resolveType** callable. <br><br> Note that you are not required to pass all of your types here - it is simply a workaround for a concrete use-case. |
| typeLoader   | `callable(string $name): Type`                      | Expected to return a type instance given the name. Must always return the same instance if called multiple times, see [lazy loading](#lazy-loading-of-types). See section below on lazy type loading.                                                                                                                                                                                                                                                         |

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

If your schema makes use of a large number of complex or dynamically-generated types, they can become a performance concern.
There are a few best practices that can lessen their impact:

1. Use a type registry.
   This will put you in a position to implement your own caching and lookup strategies, and GraphQL won't need to preload a map of all known types to do its work.

2. Define each custom type as a callable that returns a type, rather than an object instance.
   Then, the work of instantiating them will only happen as they are needed by each query.

3. Define all of your object **fields** as callbacks.
   If you're already doing #2 then this isn't needed, but it's a quick and easy precaution.

It is recommended to centralize this kind of functionality in a type registry.
A typical example might look like the following:

```php
// StoryType.php
use GraphQL\Type\Definition\ObjectType;

final class StoryType extends ObjectType
{
    public function __construct()
    {
        parent::__construct([
            'fields' => static fn (): array => [
                'author' => [
                    'type' => Types::author(),
                    'resolve' => static fn (Story $story): ?Author => DataSource::findUser($story->authorId),
                ],
            ],
        ]);
    }
}

// AuthorType.php
use GraphQL\Type\Definition\ObjectType;

final class AuthorType extends ObjectType
{
    public function __construct()
    {
        parent::__construct([
            'description' => 'Writer of books',
            'fields' => static fn (): array => [
                'firstName' => Types::string(),
            ],
        ]);
    }
}

// Types.php
use GraphQL\Type\Definition\Type;

final class Types
{
    /** @var array<string, Type&NamedType> */
    private static array $types = [];

    /** @return \Closure(): Type&NamedType */
    public static function get(string $classname): \Closure
    {
        return static fn () => self::byClassName($classname);
    }

    public static function byTypeName(string $typeName): Type&NamedType
    {
        return match ($typeName) {
            'Boolean' => self::boolean(),
            'Float' => self::float(),
            'ID' => self::id(),
            'Int' => self::int(),
            default => self::$types[$typeName] ?? throw new \Exception("Unknown GraphQL type: {$typeName}."),
        }
    }

    private static function byClassName(string $classname): Type
    {
        $parts = \explode('\\', $classname);
        $typeName = \preg_replace('~Type$~', '', $parts[\count($parts) - 1]);

        // Type loading is very similar to PHP class loading, but keep in mind
        // that the **typeLoader** must always return the same instance of a type.
        // We can enforce that in our type registry by caching known types.
        return self::$types[$typeName] ??= new $classname;
    }

    public static function author(): callable { return self::get(AuthorType::class); }
    public static function story(): callable { return self::get(StoryType::class); }

    ...
}

// api/index.php
use GraphQL\Type\Definition\ObjectType;

$schema = new Schema([
    'query' => new ObjectType([
        'name' => 'Query',
        'fields' => static fn() => [
            'story' => [
                'args'=>[
                  'id' => Types::int(),
                ],
                'type' => Types::story(),
                'description' => 'Returns my A',
                'resolve' => static fn ($rootValue, array $args): ?Story => DataSource::findStory($args['id']),
            ],
        ],
    ]),
    'typeLoader' => Types::byTypeName(...),
]);
```

A working demonstration of this kind of architecture can be found in the [01-blog](https://github.com/webonyx/graphql-php/blob/master/examples/01-blog) sample.

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
