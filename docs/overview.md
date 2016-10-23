# Concepts
GraphQL is data-centric. On the very top level it is built around three major concepts: 
**Schema**, **Query** and **Mutation**.

You are expected to expresses your application as **Schema** (aka Type System) and expose it
with single HTTP endpoint. Application clients (e.g. web or mobile clients) send **Queries** 
to this endpoint to request structured data and **Mutations** to perform changes.

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

It was designed to mirror the structure of expected response:
```json
{
  "hero": {
    "name": "R2-D2",
    "friends": [
      {"name": "Luke Skywalker"},
      {"name": "Han Solo"},
      {"name": "Leia Organa"}
    ]
  }
}
```
**graphql-php** runtime parses Queries, makes sure that they are valid for given Type System 
and executes using data resolving tools provided by you as a part of integration.
 
## Mutations
Mutations use advanced features of the very same query language (like arguments and variables)  
and have only semantic difference from Queries:
```graphql
mutation CreateReviewForEpisode($ep: Episode!, $review: ReviewInput!) {
  createReview(episode: $ep, review: $review) {
    stars
    commentary
  }
}
```
Variables `$ep` and `$review` are sent alongside with mutation. Full HTTP request might look like this:
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
**graphql-php** runtime.

Another nice feature of GraphQL mutations is that they also hold the query for data to be 
returned after mutation. In our example mutation will return:
```
{
  "createReview": {
    "stars": 5,
    "commentary": "This is a great movie!"
  }
}
```

# Type System
Type system is a heart of GraphQL integration. That's where **graphql-php** comes into play.
 
It provides following tools and primitives to describe your App as hierarchy of types:

 * Primitives to work with **objects** and **interfaces**
 * Primitives for defining **enumerations** and **unions**
 * Primitives for defining custom **scalar types**
 * Built-in scalar types: `ID`, `String`, `Int`, `Float`, `Boolean`
 * Built-in type modifiers: `ListOf` and `NonNull`

# Further Reading
To get deeper understanding of GraphQL concepts - [read the docs on official website](http://graphql.org/learn/)

To get started with your own app - continue to next section ["Getting Started"](getting-started/)