# Union Type Definition

A Union is an abstract type that simply enumerates other Object Types.
The value of Union Type is actually a value of one of included Object Types.

## Writing Union Types

In **graphql-php** union type is an instance of `GraphQL\Type\Definition\UnionType`
(or one of its subclasses) which accepts configuration array in a constructor:

```php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\UnionType;

$searchResultType = new UnionType([
    'name' => 'SearchResult',
    'types' => [
        MyTypes::story(),
        MyTypes::user()
    ],
    'resolveType' => function ($value): ObjectType {
        switch ($value->type ?? null) {
            case 'story': return MyTypes::story();
            case 'user': return MyTypes::user();
            default: throw new Exception("Unexpected SearchResult type: {$value->type ?? null}");
        }
    },
]);
```

This example uses **inline** style for Union definition, but you can also use  
[inheritance or schema definition language](index.md#definition-styles).

## Configuration options

The constructor of UnionType accepts an array. Below is a full list of allowed options:

| Option      | Type       | Notes                                                                                                                                                                                                                                    |
| ----------- | ---------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| name        | `string`   | **Required.** Unique name of this interface type within Schema                                                                                                                                                                           |
| types       | `array`    | **Required.** List of Object Types included in this Union. Note that you can't create a Union type out of Interfaces or other Unions.                                                                                                    |
| description | `string`   | Plain-text description of this type for clients (e.g. used by [GraphiQL](https://github.com/graphql/graphiql) for auto-generated documentation)                                                                                          |
| resolveType | `callback` | **function ($value, $context, [ResolveInfo](../class-reference.md#graphqltypedefinitionresolveinfo) $info): ObjectType**<br> Receives **$value** from resolver of the parent field and returns concrete Object Type for this **$value**. |
