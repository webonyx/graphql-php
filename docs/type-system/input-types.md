# About Input and Output Types
GraphQL receives data from clients via [Field Arguments](object-types/#field-arguments).

Both - fields and arguments require **type** option in definition. But expected value of this option
is different for fields and arguments, as in GraphQL argument is conceptually input while field is conceptually 
output.

Consequentially all types in GraphQL are of two categories: **input** and **output**.

* **Output** types (or field types) are: [Scalar](scalar-types/), [Enum](enum-types/), [Object](object-types/), 
[Interface](interfaces/), [Union](unions/)

* **Input** types (or argument types) are: [Scalar](scalar-types/), [Enum](enum-types/), InputObject

Obviously [NonNull and List](lists-and-nonnulls/) types belong to both categories depending on their 
inner type.

Until now all examples of field **arguments** in this documentation were of [Scalar](scalar-types/) or 
[Enum](enum-types/) types. But you can also easily pass complex objects. 

This is particularly valuable in the case of mutations, where input data might be rather complex.

# Input Object Type
GraphQL specification defines Input Object Type for complex inputs. It is similar to ObjectType
except that it's fields have no **args** or **resolve** options and their **type** must be input type.

In **graphql-php** Input Object Type is an instance of `GraphQL\Type\Definition\InputObjectType` 
(or one of it subclasses) which accepts configuration array in constructor:

```php
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

# Configuration options
Constructor of InputObjectType accepts array with only 3 options:
 
Option       | Type     | Notes
------------ | -------- | -----
name         | `string` | **Required.** Unique name of this object type within Schema
fields       | `array` or `callback` returning `array` | **Required**. Array describing object fields (see below).
description  | `string` | Plain-text description of this type for clients (e.g. used by [GraphiQL](https://github.com/graphql/graphiql) for auto-generated documentation)

Every field is an array with following entries:

Option | Type | Notes
------ | ---- | -----
name | `string` | **Required.** Name of the input field. When not set - inferred from **fields** array key
type | `Type` | **Required.** Instance of one of [Input Types](input-types/) (`Scalar`, `Enum`, `InputObjectType` + any combination of those with `NonNull` and `List` modifiers)
description | `string` | Plain-text description of this input field for clients (e.g. used by [GraphiQL](https://github.com/graphql/graphiql) for auto-generated documentation)
defaultValue | `scalar` | Default value of this input field

# Using Input Object Type
In the example above we defined our InputObjectType. Now let's use it in one of field arguments:

```php
$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'stories' => [
            'type' => Type::listOf($storyType),
            'args' => [
                'filters' => [
                    'type' => Type::nonNull($filters),
                    'defaultValue' => [
                        'popular' => true
                    ]
                ]
            ],
            'resolve' => function($rootValue, $args) {
                return DataSource::filterStories($args['filters']);
            }
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
