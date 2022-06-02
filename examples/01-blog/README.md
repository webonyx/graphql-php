# Blog Example

Simple yet full-featured example of GraphQL API.
Models a blogging platform with Stories, Users and hierarchical comments.

## Run local test server

```sh
php -S localhost:8080 graphql.php
```

### Try query

```
curl -d '{"query": "query { hello }" }' -H "Content-Type: application/json" http://localhost:8080
```

The response should be:

```json
{
  "data": {
    "hello": "Your graphql-php endpoint is ready! Use a GraphQL client to explore the schema."
  }
}
```

## Explore the Schema

The most convenient way to explore a GraphQL schema is to use a GraphQL client.
We recommend you download and install [Altair](https://altair.sirmuel.design).

Set `http://localhost:8080` as your GraphQL endpoint and try clicking the "Docs" button
to explore the schema definition.

## Running GraphQL Queries

Copy the following query to your GraphQL client and send the request:

```graphql
{
  viewer {
    id
    email
  }
  user(id: "2") {
    id
    email
  }
  stories(after: "1") {
    id
    body
    comments {
      ...CommentView
    }
  }
  lastStoryPosted {
    id
    hasViewerLiked
    author {
      id
      photo(size: ICON) {
        id
        url
        size
        width
        height
        # Uncomment following line to see validation error:
        # nonExistingField

        # Uncomment to see error reporting for fields with exceptions thrown in resolvers
        # fieldWithError
        # nonNullFieldWithError
      }
      lastStoryPosted {
        id
      }
    }
    body(format: HTML, maxLength: 10)
  }
}

fragment CommentView on Comment {
  id
  body
  totalReplyCount
  replies {
    id
    body
  }
}
```

## Run your own query

Use autocomplete (via CTRL+space) to easily create your own query.

Note: GraphQL query requires at least one field per object type (to prevent accidental overfetching).
For example, the following query is invalid in GraphQL:

```graphql
{
  viewer
}
```

### Dig into source code

Now when you tried GraphQL API as a consumer, see how it is implemented by browsing
the source code.
