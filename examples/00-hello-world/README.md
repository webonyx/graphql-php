# Hello world

Clean and simple single-file example of main GraphQL concepts originally proposed and
implemented by [Leo Cavalcante](https://github.com/leocavalcante).

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
