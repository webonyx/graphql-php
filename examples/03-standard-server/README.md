# Hello world

Same as the Hello World example, but uses the [Standard Http Server](https://webonyx.github.io/graphql-php/executing-queries/#using-server)
instead of manually parsing the incoming data.

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
