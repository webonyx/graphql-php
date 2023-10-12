## Prerequisites

This documentation assumes your familiarity with GraphQL concepts. If it is not the case -
first learn about GraphQL on [the official website](https://graphql.org/learn/).

## Installation

Using [composer](https://getcomposer.org/doc/00-intro.md), run:

```sh
composer require webonyx/graphql-php
```

## Upgrading

We try to keep library releases backwards compatible when possible.
For breaking changes we provide [upgrade instructions](https://github.com/webonyx/graphql-php/blob/master/UPGRADE.md).

## Install Tools (optional)

While it is possible to communicate with GraphQL API using regular HTTP tools it is way
more convenient for humans to use [GraphiQL](https://github.com/graphql/graphiql) - an in-browser
IDE for exploring GraphQL APIs.

It provides syntax-highlighting, auto-completion and auto-generated documentation for
GraphQL API.

The easiest way to use it is to install one of the existing Google Chrome extensions:

- [ChromeiQL](https://chrome.google.com/webstore/detail/chromeiql/fkkiamalmpiidkljmicmjfbieiclmeij)
- [GraphiQL Feen](https://chrome.google.com/webstore/detail/graphiql-feen/mcbfdonlkfpbfdpimkjilhdneikhfklp)

Alternatively, you can follow instructions on [the GraphiQL](https://github.com/graphql/graphiql)
page and install it locally.

## Hello World

Let's create a type system that will be capable to process the following simple query:

```
query {
  echo(message: "Hello World")
}
```

We need an object type with the field `echo`:

```php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'echo' => [
            'type' => Type::string(),
            'args' => [
                'message' => Type::nonNull(Type::string()),
            ],
            'resolve' => fn ($rootValue, array $args): string => $rootValue['prefix'] . $args['message'],
        ],
    ],
]);

```

(Note: type definition can be expressed in [different styles](type-definitions/index.md#definition-styles))

The interesting piece here is the **resolve** option of the field definition. It is responsible for returning
a value of our field. Values of **scalar** fields will be directly included in the response while values of
**composite** fields (objects, interfaces, unions) will be passed down to nested field resolvers
(not in this example though).

Now when our type is ready, let's create a GraphQL endpoint file for it **graphql.php**:

```php
use GraphQL\GraphQL;
use GraphQL\Type\Schema;

$schema = new Schema([
    'query' => $queryType
]);

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$query = $input['query'];
$variableValues = isset($input['variables']) ? $input['variables'] : null;

try {
    $rootValue = ['prefix' => 'You said: '];
    $result = GraphQL::executeQuery($schema, $query, $rootValue, null, $variableValues);
    $output = $result->toArray();
} catch (\Exception $e) {
    $output = [
        'errors' => [
            [
                'message' => $e->getMessage()
            ]
        ]
    ];
}
header('Content-Type: application/json');
echo json_encode($output, JSON_THROW_ON_ERROR);
```

Our example is finished. Try it by running:

```sh
php -S localhost:8080 graphql.php
curl http://localhost:8080 -d '{"query": "query { echo(message: \"Hello World\") }" }'
```

Check out the full [source code](https://github.com/webonyx/graphql-php/blob/master/examples/00-hello-world) of this example
which also includes simple mutation.

Check out [the blog example](https://github.com/webonyx/graphql-php/blob/master/examples/01-blog) for something
which is closer to real-world apps or read about the details of [schema definition](schema-definition.md).

## Next Steps

Obviously hello world only scratches the surface of what is possible.

To learn by example, check out the [blog example](https://github.com/webonyx/graphql-php/tree/master/examples/01-blog)
which is quite close to real-world GraphQL hierarchies.

For a deeper understanding of GraphQL in general, check out [concepts](concepts.md).

To delve right into the implementation, see [schema definition](schema-definition.md).
