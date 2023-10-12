# Type Definitions

graphql-php represents a **type** as a class instance from the `GraphQL\Type\Definition` namespace:

- [`ObjectType`](object-types.md)
- [`InterfaceType`](interfaces.md)
- [`UnionType`](unions.md)
- [`InputObjectType`](inputs.md)
- [`ScalarType`](scalars.md)
- [`EnumType`](enums.md)

## Input vs. Output Types

All types in GraphQL are of two categories: **input** and **output**.

- **Output** types (or field types) are: [Scalar](scalars.md), [Enum](enums.md), [Object](object-types.md),
  [Interface](interfaces.md), [Union](unions.md)

- **Input** types (or argument types) are: [Scalar](scalars.md), [Enum](enums.md), [Inputs](inputs.md)

Obviously, [NonNull and List](lists-and-nonnulls.md) types belong to both categories depending on their
inner type.

## Definition styles

Several styles of type definitions are supported depending on your preferences.

### Inline definitions

```php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

$myType = new ObjectType([
    'name' => 'MyType',
    'fields' => [
        'id' => Type::id()
    ]
]);
```

### Class per type

```php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class MyType extends ObjectType
{
    public function __construct()
    {
        $config = [
            // Note: 'name' is not needed in this form:
            // it will be inferred from class name by omitting namespace and dropping "Type" suffix
            'fields' => [
                'id' => Type::id()
            ]
        ];
        parent::__construct($config);
    }
}
```

### Schema definition language

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

Read more about [building an executable schema using schema definition language](../schema-definition-language.md).
