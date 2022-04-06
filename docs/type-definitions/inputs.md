# Input Object Type Definition
The GraphQL specification defines Input Object Type for complex inputs. It is similar to ObjectType
except that it's fields have no **args** or **resolve** options and their **type** must be input type.

## Writing Input Object Types
In graphql-php **Input Object Type** is an instance of `GraphQL\Type\Definition\InputObjectType` 
(or one of its subclasses) which accepts configuration array in its constructor:

```php
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\InputObjectType;

$filters = new InputObjectType([
    'name' => 'StoryFiltersInput',
    'fields' => [
        'author' => [
            'type' => Type::id(),
            'description' => 'Only show stories with this author id'
        ],
        'popular' => [
            'type' => Type::boolean(),
            'description' => 'Only show popular stories (liked by several people)'
        ],
        'tags' => [
            'type' => Type::listOf(Type::string()),
            'description' => 'Only show stories which contain all of those tags'
        ]
    ]
]);
```

Every field may be of other InputObjectType (thus complex hierarchies of inputs are possible)

## Configuration options
The constructor of InputObjectType accepts an array with only 3 options:
 
Option       | Type     | Notes
------------ | -------- | -----
name         | `string` | **Required.** Unique name of this object type within Schema
fields       | `array` or `callable` | **Required**. An array describing object fields or callable returning such an array (see below).
description  | `string` | Plain-text description of this type for clients (e.g. used by [GraphiQL](https://github.com/graphql/graphiql) for auto-generated documentation)

Every field is an array with following entries:

Option | Type | Notes
------ | ---- | -----
name | `string` | **Required.** Name of the input field. When not set - inferred from **fields** array key
type | `Type` | **Required.** Instance of one of [Input Types](inputs.md) (**Scalar**, **Enum**, **InputObjectType** + any combination of those with **nonNull** and **listOf** modifiers)
description | `string` | Plain-text description of this input field for clients (e.g. used by [GraphiQL](https://github.com/graphql/graphiql) for auto-generated documentation)
defaultValue | `scalar` | Default value of this input field. Use the internal value if specifying a default for an **enum** type

## Using Input Object Type
In the example above we defined our InputObjectType. Now let's use it in one of field arguments:

```php
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'stories' => [
            'type' => Type::listOf($storyType),
            'args' => [
                'filters' => [
                    'type' => $filters,
                    'defaultValue' => [
                        'popular' => true
                    ]
                ]
            ],
            'resolve' => fn ($rootValue, array $args): array => DataSource::filterStories($args['filters']),
        ]
    ]
]);
```

(note that you can define **defaultValue** for fields with complex inputs as associative array).

Then GraphQL query could include filters as literal value:
```graphql
{
    stories(filters: {author: "1", popular: false})
}
```

Or as query variable:
```graphql
query($filters: StoryFiltersInput!) {
    stories(filters: $filters)
}
```
```php
$variables = [
    'filters' => [
        "author" => "1",
        "popular" => false
    ]
];
```

**graphql-php** will validate the input against your InputObjectType definition and pass it to your 
resolver as `$args['filters']`
