# Hello world

Same as the Hello World example, but uses the [Standard Http Server](https://webonyx.github.io/graphql-php/executing-queries/#using-server)
instead of manually parsing the incoming data.

### Run locally
```
php -S localhost:8080 ./graphql.php
```

### Try query
```
curl -d '{"query": "query { echo(message: \"Hello World\") }" }' -H "Content-Type: application/json" http://localhost:8080
```

### Try mutation
```
curl -d '{"query": "mutation { sum(x: 2, y: 2) }" }' -H "Content-Type: application/json" http://localhost:8080
```
