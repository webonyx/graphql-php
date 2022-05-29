## Overview

GraphQL is data-centric. On the very top level it is built around three major concepts:
**Schema**, **Query** and **Mutation**.

You are expected to express your application as a **Schema** (aka Type System) and expose it
as a single HTTP endpoint (e.g. using our [standard server](executing-queries.md#using-server)).
Application clients (e.g. web or mobile clients) send **Queries**
to this endpoint to request structured data and **Mutations** to perform changes (usually with HTTP POST method).

## Queries

Queries are expressed in simple language that resembles JSON:

```graphql
{
  hero {
    name
    friends {
      name
    }
  }
}
```

It was designed to mirror the structure of the expected response:

```json
{
  "hero": {
    "name": "R2-D2",
    "friends": [
      { "name": "Luke Skywalker" },
      { "name": "Han Solo" },
      { "name": "Leia Organa" }
    ]
  }
}
```

The **graphql-php** runtime parses Queries, makes sure that they are valid for a given Type System
and executes using [data fetching tools](data-fetching.md) provided by you
as part of the integration. Queries are supposed to be idempotent.

## Mutations

Mutations are root fields that are allowed to have side effects, such as creating, updating or deleting data.
In contrast to Query fields, the fields within the root Mutation type are executed serially.
Otherwise, their definition and execution is identical to all other fields.

```graphql
mutation CreateReviewForEpisode($ep: Episode!, $review: ReviewInput!) {
  createReview(episode: $ep, review: $review) {
    stars
    commentary
  }
}
```

Variables `$ep` and `$review` are sent alongside with the mutation. A full HTTP request might look like this:

```json
// POST /graphql-endpoint
// Content-Type: application/javascript
//
{
  "query": "mutation CreateReviewForEpisode...",
  "variables": {
    "ep": "JEDI",
    "review": {
      "stars": 5,
      "commentary": "This is a great movie!"
    }
  }
}
```

As you see variables may include complex objects and they will be correctly validated by
the **graphql-php** runtime.

Another nice feature of GraphQL mutations is that they also hold the query for data to be
returned after mutation. In our example the mutation will return:

```
{
  "createReview": {
    "stars": 5,
    "commentary": "This is a great movie!"
  }
}
```

## Type System

Conceptually a GraphQL type is a collection of fields. Each field in turn
has its own type which allows building complex hierarchies.

Quick example on pseudo-language:

```
type BlogPost {
    title: String!
    author: User
    body: String
}

type User {
    id: Id!
    firstName: String
    lastName: String
}
```

The type system is at the heart of GraphQL integration. That's where **graphql-php** comes into play.

It provides the following tools and primitives to describe your App as a hierarchy of types:

- Primitives for defining **objects** and **interfaces**
- Primitives for defining **enumerations** and **unions**
- Primitives for defining custom **scalar types**
- Built-in scalar types: `ID`, `String`, `Int`, `Float`, `Boolean`
- Built-in type modifiers: `ListOf` and `NonNull`

Same example expressed in **graphql-php**:

```php
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;

$userType = new ObjectType([
    'name' => 'User',
    'fields' => [
        'id' => Type::nonNull(Type::id()),
        'firstName' => Type::string(),
        'lastName' => Type::string()
    ]
]);

$blogPostType = new ObjectType([
    'name' => 'BlogPost',
    'fields' => [
        'title' => Type::nonNull(Type::string()),
        'author' => $userType
    ]
]);
```

## Further Reading

To get deeper understanding of GraphQL concepts - [read the docs on official GraphQL website](https://graphql.org/learn/)

To get started with **graphql-php** - continue to next section ["Getting Started"](getting-started.md)
