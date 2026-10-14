# Schema Definition Language

Same as the Hello world example, but shows how to use schema definition language
and wire up some resolvers as plain functions.

### Run local test server

```
php -S localhost:8080 graphql.php
```

### Try query

```
curl --data '{"query": "query { echo(message: \"Hello World\") }" }' --header "Content-Type: application/json" http://localhost:8080
```

### Try mutation

```
curl --data '{"query": "mutation { sum(x: 2, y: 2) }" }' --header "Content-Type: application/json" http://localhost:8080
```
