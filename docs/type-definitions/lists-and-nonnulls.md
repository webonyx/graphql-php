## Lists

**graphql-php** provides built-in support for lists. In order to create list type - wrap
existing type with `GraphQL\Type\Definition\Type::listOf()` modifier:

```php
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;

$userType = new ObjectType([
    'name' => 'User',
    'fields' => [
        'emails' => [
            'type' => Type::listOf(Type::string()),
            'resolve' => fn (): array => [
                'jon@example.com',
                'jonny@example.com'
            ],
        ]
    ]
]);
```

Resolvers for such fields are expected to return **array** or instance of PHP's built-in **Traversable**
interface (**null** is allowed by default too).

If returned value is not of one of these types - **graphql-php** will add an error to result
and set the field value to **null** (only if the field is nullable, see below for non-null fields).

## Non-Nulls

By default, every field or argument can have a **null** value.
To indicate the value must be **non-null** use the `GraphQL\Type\Definition\Type::nonNull()` modifier:

```php
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;

$humanType = new ObjectType([
    'name' => 'User',
    'fields' => [
        'id' => [
            'type' => Type::nonNull(Type::id()),
            'resolve' => fn (): string => uniqid(),
        ],
        'emails' => [
            'type' => Type::nonNull(Type::listOf(Type::string())),
            'resolve' => fn (): array => [
                'jon@example.com',
                'jonny@example.com'
            ],
        ]
    ]
]);
```

If resolver of non-null field returns **null**, graphql-php will add an error to
result and exclude the whole object from the output (an error will bubble to first
nullable parent field which will be set to **null**).

Read the section on [Data Fetching](../data-fetching.md) for details.
