# Hello world
Same example as 01-hello-world, but uses 
[Standard Http Server](http://webonyx.github.io/graphql-php/executing-queries/#using-server)
instead of manual parsing of incoming data.

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
