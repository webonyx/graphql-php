# Schema Definition Language

Same as the Hello world example, but shows how to use schema definition language
and wire up some resolvers as plain functions.

### Run locally
```
php -S localhost:8080 ./graphql.php
```

### Try query
```
curl http://localhost:8080 -d '{"query": "query { echo(message: \"Hello World\") }" }'
```

### Try mutation
```
curl http://localhost:8080 -d '{"query": "mutation { sum(x: 2, y: 2) }" }'
```
