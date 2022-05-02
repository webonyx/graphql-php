# Schema Definition Language

Same as the Hello world example, but shows how to use schema definition language
and wire up some resolvers as plain functions.

### Run local test server

```
php -S localhost:8080 graphql.php
```

### Try query

```
curl -d '{"query": "query { echo(message: \"Hello World\") }" }' -H "Content-Type: application/json" http://localhost:8080
```

### Try mutation

```
curl -d '{"query": "mutation { sum(x: 2, y: 2) }" }' -H "Content-Type: application/json" http://localhost:8080
```
