# Enum Type Definition

Enumeration types are a special kind of scalar that is restricted to a particular set
of allowed values.

## Writing Enum Types

In **graphql-php** enum type is an instance of `GraphQL\Type\Definition\EnumType`
which accepts configuration array in constructor:

```php
use GraphQL\Type\Definition\EnumType;

$episodeEnum = new EnumType([
    'name' => 'Episode',
    'description' => 'One of the films in the Star Wars Trilogy',
    'values' => [
        'NEWHOPE' => [
            'value' => 4,
            'description' => 'Released in 1977.'
        ],
        'EMPIRE' => [
            'value' => 5,
            'description' => 'Released in 1980.'
        ],
        'JEDI' => [
            'value' => 6,
            'description' => 'Released in 1983.'
        ],
    ]
]);
```

This example uses an **inline** style for Enum Type definition, but you can also use
[inheritance or schema definition language](index.md#definition-styles).

## Configuration options

Enum Type constructor accepts an array with following options:

| Option      | Type     | Notes                                                                                                                                          |
| ----------- | -------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| name        | `string` | **Required.** Name of the type. When not set - inferred from array key (read about [shorthand field definition](#shorthand-definitions) below) |
| description | `string` | Plain-text description of the type for clients (e.g. used by [GraphiQL](https://github.com/graphql/graphiql) for auto-generated documentation) |
| values      | `array`  | List of enumerated items, see below for expected structure of each entry                                                                       |

Each entry of **values** array in turn accepts following options:

| Option            | Type     | Notes                                                                                                                                            |
| ----------------- | -------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| name              | `string` | **Required.** Name of the item. When not set - inferred from array key (read about [shorthand field definition](#shorthand-definitions) below)   |
| value             | `mixed`  | Internal representation of enum item in your application (could be any value, including complex objects or callbacks)                            |
| description       | `string` | Plain-text description of enum value for clients (e.g. used by [GraphiQL](https://github.com/graphql/graphiql) for auto-generated documentation) |
| deprecationReason | `string` | Text describing why this enum value is deprecated. When not empty - item will not be returned by introspection queries (unless forced)           |

## Construction from PHP enum

You can reuse your existing PHP enums in GraphQL.
Leverage PHP attributes to add descriptions and deprecate values:

```php
use GraphQL\Type\Definition\Deprecated;
use GraphQL\Type\Definition\Description;

#[Description(description: 'Sweet and juicy.')]
enum Fruit
{
    #[Description(description: 'Rich in potassium.')]
    case BANANA;

    #[Deprecated(reason: 'Too sour.')]
    case CITRON;
}
```

PhpDocs will be used as descriptions if present, but are overridden by the `Description` attribute.

Wrap them with `GraphQL\Type\Definition\PhpEnumType` to use them in a GraphQL schema:

```php
use GraphQL\Type\Definition\PhpEnumType;

$fruitEnumType = new PhpEnumType(Fruit::class);
```

The following type will be deduced from `Fruit`:

```graphql
"""
Sweet and juicy.
"""
enum Fruit {
  """
  Rich in potassium.
  """
  BANANA

  CITRON @deprecated(reason: "Too sour.")
}
```

Conversion rules:

- Client-given arguments will be converted to enum instances
- Returned output values are expected to be enum instances

## Shorthand definitions

If internal representation of enumerated item is the same as item name, then you can use
following shorthand for definition:

```php
use GraphQL\Type\Definition\EnumType;

$episodeEnum = new EnumType([
    'name' => 'Episode',
    'description' => 'One of the films in the Star Wars Trilogy',
    'values' => ['NEWHOPE', 'EMPIRE', 'JEDI']
]);
```

which is equivalent of:

```php
use GraphQL\Type\Definition\EnumType;

$episodeEnum = new EnumType([
    'name' => 'Episode',
    'description' => 'One of the films in the Star Wars Trilogy',
    'values' => [
        'NEWHOPE' => ['value' => 'NEWHOPE'],
        'EMPIRE' => ['value' => 'EMPIRE'],
        'JEDI' => ['value' => 'JEDI']
    ]
]);
```

which is in turn equivalent of the full form:

```php
use GraphQL\Type\Definition\EnumType;

$episodeEnum = new EnumType([
    'name' => 'Episode',
    'description' => 'One of the films in the Star Wars Trilogy',
    'values' => [
        ['name' => 'NEWHOPE', 'value' => 'NEWHOPE'],
        ['name' => 'EMPIRE', 'value' => 'EMPIRE'],
        ['name' => 'JEDI', 'value' => 'JEDI']
    ]
]);
```

## Field Resolution

When object field is of Enum Type, field resolver is expected to return an internal
representation of corresponding Enum item (**value** in config). **graphql-php** will
then serialize this **value** to **name** to include in response:

```php
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;

$episodeEnum = new EnumType([
    'name' => 'Episode',
    'description' => 'One of the films in the Star Wars Trilogy',
    'values' => [
        'NEWHOPE' => [
            'value' => 4,
            'description' => 'Released in 1977.'
        ],
        'EMPIRE' => [
            'value' => 5,
            'description' => 'Released in 1980.'
        ],
        'JEDI' => [
            'value' => 6,
            'description' => 'Released in 1983.'
        ],
    ]
]);

$heroType = new ObjectType([
    'name' => 'Hero',
    'fields' => [
        'appearsIn' => [
            'type' => $episodeEnum,
             // Actual entry in response will be 'appearsIn' => 'EMPIRE'
            'resolve' => fn (): int => 5,
        ]
    ]
]);
```

The Reverse is true when the enum is used as input type (e.g. as field argument).
GraphQL will treat enum input as **name** and convert it into **value** before passing to your app.

For example, given object type definition:

```php
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;

$heroType = new ObjectType([
    'name' => 'Hero',
    'fields' => [
        'appearsIn' => [
            'type' => Type::boolean(),
            'args' => [
                'episode' => Type::nonNull($enumType)
            ],
            'resolve' => fn ($hero, array $args): bool => $args['episode'] === 5,
        ]
    ]
]);
```

Then following query:

```graphql
fragment on Hero {
    appearsInNewHope: appearsIn(NEWHOPE)
    appearsInEmpire: appearsIn(EMPIRE)
}
```

will return:

```php
[
    'appearsInNewHope' => false,
    'appearsInEmpire' => true
]
```
