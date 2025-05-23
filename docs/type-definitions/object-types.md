# Object Type Definition

Object Type is the most frequently used primitive in a typical GraphQL application.

Conceptually Object Type is a collection of Fields. Each field, in turn,
has its own type which allows building complex hierarchies.

## Writing Object Types

In **graphql-php** object type is an instance of `GraphQL\Type\Definition\ObjectType`
(or one of its subclasses) which accepts a configuration array in its constructor:

```php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Data\Story;

$userType = new ObjectType([
    'name' => 'User',
    'description' => 'Our blog visitor',
    'fields' => [
        'firstName' => [
            'type' => Type::string(),
            'description' => 'User first name'
        ],
        'email' => Type::string()
    ]
]);

$blogStory = new ObjectType([
    'name' => 'Story',
    'fields' => [
        'body' => Type::string(),
        'author' => [
            'type' => $userType,
            'description' => 'Story author',
            'resolve' => fn (Story $blogStory): ?User => DataSource::findUser($blogStory->authorId),
        ],
        'likes' => [
            'type' => Type::listOf($userType),
            'description' => 'List of users who liked the story',
            'args' => [
                'limit' => [
                    'type' => Type::int(),
                    'description' => 'Limit the number of recent likes returned',
                    'defaultValue' => 10
                ]
            ],
            'resolve' => fn (Story $blogStory, array $args): array => DataSource::findLikes($blogStory->id, $args['limit']),
        ]
    ]
]);
```

This example uses **inline** style for Object Type definitions, but you can also use  
[inheritance or schema definition language](index.md#definition-styles).

## Configuration options

| Option       | Type                  | Notes                                                                                                                                                                                                                                                                                                                                                                           |
|--------------|-----------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| name         | `string`              | **Required.** Unique name of this object type within Schema                                                                                                                                                                                                                                                                                                                     |
| fields       | `array` or `callable` | **Required**. An array describing object fields or callable returning such an array. See [field configuration options](#field-configuration-options) section below for expected structure of each array entry. See also the section on [Circular types](#recurring-and-circular-types) for an explanation of when to use callable for this option.                              |
| description  | `string`              | Plain-text description of this type for clients (e.g. used by [GraphiQL](https://github.com/graphql/graphiql) for auto-generated documentation)                                                                                                                                                                                                                                 |
| interfaces   | `array` or `callable` | List of interfaces implemented by this type or callable returning such a list. See [Interface Types](interfaces.md) for details. See also the section on [Circular types](#recurring-and-circular-types) for an explanation of when to use callable for this option.                                                                                                            |
| isTypeOf     | `callable`            | **function ($value, $context, [ResolveInfo](../class-reference.md#graphqltypedefinitionresolveinfo) $info): bool**<br> Expected to return **true** if **$value** qualifies for this type (see section about [Abstract Type Resolution](interfaces.md#interface-role-in-data-fetching) for explanation).                                                                         |
| resolveField | `callable`            | **function ($value, array $args, $context, [ResolveInfo](../class-reference.md#graphqltypedefinitionresolveinfo) $info): mixed**<br> Given the **$value** of this type, it is expected to return value for a field defined in **$info->fieldName**. A good place to define a type-specific strategy for field resolution. See [Data Fetching](../data-fetching.md) for details. |
| argsMapper   | `callable`            | **function (array $args, FieldDefinition, FieldNode): mixed**<br> Called once, when Executor resolves arguments for given field. Could be used to validate args and/or to map them to DTO/Object.                                                                                                                                                                               |
| visible      | `bool` or `callable`  | Defaults to `true`. The given callable receives no arguments and is expected to return a `bool`, it is called once when the field may be accessed. The field is treated as if it were not defined at all when this is `false`.                                                                                                                                                  |

### Field configuration options

| Option            | Type       | Notes                                                                                                                                                                                                                                                                                                           |
| ----------------- | ---------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| name              | `string`   | **Required.** Name of the field. When not set - inferred from **fields** array key (read about [shorthand field definition](#shorthand-field-definitions) below)                                                                                                                                                |
| type              | `Type`     | **Required.** An instance of internal or custom type. Note: type must be represented by a single instance within one schema (see also [lazy loading of types](../schema-definition.md#lazy-loading-of-types))                                                                                                   |
| args              | `array`    | An array describing any number of possible field arguments, each element being an array. See [field argument configuration options](#field-argument-configuration-options).                                                                                                                                     |
| resolve           | `callable` | **function ($objectValue, array $args, $context, [ResolveInfo](../class-reference.md#graphqltypedefinitionresolveinfo) $info): mixed**<br> Given the **$objectValue** of this type, it is expected to return actual value of the current field. See section on [Data Fetching](../data-fetching.md) for details |
| complexity        | `callable` | **function (int $childrenComplexity, array $args): int**<br> Used to restrict query complexity. The feature is disabled by default, read about [Security](../security.md#query-complexity-analysis) to use it.                                                                                                  |
| description       | `string`   | Plain-text description of this field for clients (e.g. used by [GraphiQL](https://github.com/graphql/graphiql) for auto-generated documentation)                                                                                                                                                                |
| deprecationReason | `string`   | Text describing why this field is deprecated. When not empty - field will not be returned by introspection queries (unless forced)                                                                                                                                                                              |

### Field argument configuration options

| Option       | Type     | Notes                                                                                                                                                                      |
| ------------ | -------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| name         | `string` | **Required.** Name of the argument. When not set - inferred from **args** array key                                                                                        |
| type         | `Type`   | **Required.** Instance of one of [Input Types](inputs.md) (**scalar**, **enum**, **InputObjectType** + any combination of those with **nonNull** and **listOf** modifiers) |
| description  | `string` | Plain-text description of this argument for clients (e.g. used by [GraphiQL](https://github.com/graphql/graphiql) for auto-generated documentation)                        |
| defaultValue | `scalar` | Default value for this argument. Use the internal value if specifying a default for an **enum** type                                                                       |

## Shorthand field definitions

Fields can be also defined in **shorthand** notation (with only **name** and **type** options):

```php
'fields' => [
    'id' => Type::id(),
    'fieldName' => $fieldType
]
```

which is equivalent of:

```php
'fields' => [
    'id' => ['type' => Type::id()],
    'fieldName' => ['type' => $fieldName]
]
```

which is in turn equivalent of the full form:

```php
'fields' => [
    ['name' => 'id', 'type' => Type::id()],
    ['name' => 'fieldName', 'type' => $fieldName]
]
```

Same shorthand notation applies to field arguments as well.

## Recurring and circular types

Almost all real-world applications contain recurring or circular types.
Think user friends or nested comments for example.

**graphql-php** allows such types, but you have to use `callable` in
option **fields** (and/or **interfaces**).

For example:

```php
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;

$userType = new ObjectType([
    'name' => 'User',
    'fields' => function () use (&$userType): array {
        return [
            'email' => [
                'type' => Type::string()
            ],
            'friends' => [
                'type' => Type::listOf($userType)
            ]
        ];
    },
]);
```

Same example for [inheritance style of type definitions](index.md#definition-styles)
using a type registry (see [lazy loading of types](../schema-definition.md#lazy-loading-of-types)):

```php
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;

class UserType extends ObjectType
{
    public function __construct()
    {
        parent::__construct([
            'fields' => [
                'email' => fn (): ScalarType => MyTypes::string(),
                'friends' => fn (): ListOfType => MyTypes::listOf(MyTypes::user())
            ],
        ]);
    }
}

class MyTypes
{
    private static UserType $user;

    public static function user(): UserType
    {
        return self::$user ??= new UserType();
    }

    public static function string(): ScalarType
    {
        return Type::string();
    }

    public static function listOf($type): ListOfType
    {
        return Type::listOf($type);
    }
}
```

## Field Resolution

Field resolution is the primary mechanism in **graphql-php** for returning actual data for your fields.
It is implemented using a **resolveField** callable in type definitions,
or a **resolve** callable in field definitions (the latter has precedence).

Read the section on [Data Fetching](../data-fetching.md) for a complete description of this process.

## Custom Metadata

All types in **graphql-php** accept a configuration array.
In some cases, you may be interested in passing your own metadata for type or field definition.

**graphql-php** preserves the original configuration array in every type or field instance in a public property **$config**.
You may use it for custom implementations that are not natively supported by this library.
