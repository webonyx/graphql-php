# Input Object Type Definition

The GraphQL specification defines the Input Object type for complex inputs.
It is similar to the Object type, but its fields have no **args** or **resolve** options and their **type** must be input type.

## Writing Input Object Types

In graphql-php, **Input Object Type** is an instance of `GraphQL\Type\Definition\InputObjectType`
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

The constructor of `InputObjectType` accepts an `array` with the following options:

| Option      | Type                                                           | Notes                                                                                                                                           |
|-------------|----------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------|
| name        | `string`                                                       | **Required.** Unique name of this object type within Schema                                                                                     |
| description | `string`                                                       | Plain-text description of this type for clients (e.g. used by [GraphiQL](https://github.com/graphql/graphiql) for auto-generated documentation) |
| isOneOf     | `bool`                                                         | Indicates that an Input Object is a OneOf Input Object (and thus requires exactly one of its fields be provided).                               |
| parseValue  | `callable(array<string, mixed>): mixed`                        | Converts incoming values from their array representation to something else (e.g. a value object)                                                |
| fields      | `iterable<FieldConfig>` or `callable(): iterable<FieldConfig>` | **Required**. An iterable describing object fields or callable returning such an iterable (see below).                                          |

Every entry in `fields` is an array with following entries:

| Option            | Type     | Notes                                                                                                                                                                      |
|-------------------|----------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| name              | `string` | **Required.** Name of the input field. When not set - inferred from **fields** array key                                                                                   |
| type              | `Type`   | **Required.** Instance of one of [Input Types](inputs.md) (**Scalar**, **Enum**, **InputObjectType** + any combination of those with **nonNull** and **listOf** modifiers) |
| defaultValue      | `scalar` | Default value of this input field. Use the internal value if specifying a default for an **enum** type                                                                     |
| description       | `string` | Plain-text description of this input field for clients (e.g. used by [GraphiQL](https://github.com/graphql/graphiql) for auto-generated documentation)                     |
| deprecationReason | `string` | Plain-test reason for why this input field is deprecated.                                                                                                                  |

## Using Input Object Type

In the example above we defined our InputObjectType `StoryFiltersInput`.
Now let's use it in one of field arguments:

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
                        'popular' => true,
                    ],
                ],
            ],
            'resolve' => fn ($rootValue, array $args): array => DataSource::filterStories($args['filters']),
        ],
    ],
]);
```

You can define **defaultValue** for fields with complex inputs as an associative array.

Then GraphQL query could include filters as a literal value:

```graphql
{
  stories(filters: {
    author: "1"
    popular: false
  }) {
    ...
  }
}
```

Or as a query variable:

```graphql
query ($filters: StoryFiltersInput!) {
  stories(filters: $filters) {
    ...
  }
}
```

```json
{
  "filters": {
    "author": "1",
    "popular": false
  }
}
```

**graphql-php** will validate the input against your InputObjectType definition and pass it to your resolver as `$args['filters']`.

## Converting Input Array to Value Object

If you want more type safety you can choose to parse the input array into a value object.

```php
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\InputObjectType;

final readonly class StoryFiltersInput
{
    public function __construct(
        public string $author,
        public ?bool $popular,
        /** @var array<string> */
        public array $tags
    ) {}
}

$filters = new InputObjectType([
    'name' => 'StoryFiltersInput',
    'fields' => [
        'author' => [
            'type' => Type::nonNull(Type::string()),
        ],
        'popular' => [
            'type' => Type::boolean(),
        ],
        'tags' => [
            'type' => Type::nonNull(Type::listOf(Type::string())),
        ]
    ],
    'parseValue' => fn (array $values): StoryFiltersInput => new StoryFiltersInput(
        author: $values['author'],
        popular: $values['popular'] ?? null,
        tags: $values['tags'],
    ),
]);
```

The value of `$args['filters']` will now be an instance of `StoryFiltersInput`.

The incoming values are converted using a depth-first traversal.
Thus, nested input values will be passed through their respective `parseValue` functions before the parent receives their value.

## Using the `isOneOf` Configuration Option

The `isOneOf` configuration option allows you to declare an input object as a *OneOf Input Object*.
This means that exactly one of its fields must be provided when the input is used.
This is useful when an argument can accept several alternative values, but never more than one at the same time.

Suppose you want to allow a query to filter stories either by author **or** by tag, but not both together.
You can define an input object with `isOneOf: true`:

```php
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\InputObjectType;

$storySearch = new InputObjectType([
    'name' => 'StorySearchInput',
    'isOneOf' => true,
    'fields' => [
        'author' => [
            'type' => Type::id(),
            'description' => 'Find stories by a specific author',
        ],
        'tag' => [
            'type' => Type::string(),
            'description' => 'Find stories with a specific tag',
        ],
    ],
]);
```

This input object can then be used as an argument in a query:

```php
$searchType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'searchStories' => [
            'type' => Type::listOf($storyType),
            'args' => [
                'search' => [
                    'type' => $storySearch,
                ],
            ],
            'resolve' => fn ($rootValue, array $args): array => DataSource::searchStories($args['search']),
        ],
    ],
]);
```

```graphql
{
  searchStories(search: { author: "1" }) {
    id
    title
  }
}
```

If you try to provide both fields at once, validation will fail:

```graphql
{
  searchStories(search: { author: "1", tag: "php" }) {
    id
    title
  }
}
```

**graphql-php** ensures that with `isOneOf: true`, exactly one field is set.
This helps make APIs clearer and less error-prone when alternative inputs are required.
