# Installation

Using [composer](https://getcomposer.org/doc/00-intro.md):
add `composer.json` file to your project root folder with following contents:
```
{
    "require": {
        "webonyx/graphql-php": "^0.8"
    }
}
```
and run `composer install`. 

If you already have composer.json file - simply run: `composer require webonyx/graphql-php`

If you are upgrading, see [upgrade instructions](https://github.com/webonyx/graphql-php/blob/master/UPGRADE.md)

# Install Tools (optional)
While it is possible to communicate with GraphQL API using regular HTTP tools it is way 
more convenient for humans to use [GraphiQL](https://github.com/graphql/graphiql) - an in-browser 
ide for exploring GraphQL APIs.

It provides syntax-highlighting, auto-completion and auto-generated documentation for 
GraphQL API.

The easiest way to use it is to install one of the existing Google Chrome extensions:

 - [ChromeiQL](https://chrome.google.com/webstore/detail/chromeiql/fkkiamalmpiidkljmicmjfbieiclmeij)
 - [GraphiQL Feen](https://chrome.google.com/webstore/detail/graphiql-feen/mcbfdonlkfpbfdpimkjilhdneikhfklp)

Alternatively you can follow instructions on [GraphiQL](https://github.com/graphql/graphiql)
page and install it locally.


# Hello World
Let's create type system that will be capable to process following simple query:
```
query { 
  echo(message: "Hello World")
}
```

To do so we need an object type with field `echo`:

```php
<?php
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
            'resolve' => function ($root, $args) {
                return $root['prefix'] . $args['message'];
            }
        ],
    ],
]);
```

Same could be written as separate class:

```php
<?php
namespace MyApp\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class QueryType extends ObjectType
{
    public function __construct()
    {
        $config = [
            // Note: name is not required in this form, as it will be inferred
            // from className ("Type" suffix will be dropped)
            'fields' => [
                'echo' => [
                    'type' => Type::string(),
                    'args' => [
                        'message' => Type::nonNull(Type::string()),
                    ],
                    'resolve' => function ($root, $args) {
                        return $root['prefix'] . $args['message'];
                    }
                ],
            ],
       ];
       parent::__construct($config);
    }
}
```

Or for those who prefer composition over inheritance:
```php
<?php
namespace MyApp\Type;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\DefinitionContainer;

class QueryType implements DefinitionContainer
{
    private $definition;
    
    public function getDefinition() 
    {
        return $this->definition ?: ($this->definition = new \GraphQL\Type\Definition\ObjectType([
            'name' => 'Query',
            'fields' => [
                'echo' => [
                    'type' => Type::string(),
                    'args' => [
                        'message' => Type::nonNull(Type::string()),
                    ],
                    'resolve' => function ($root, $args) {
                        return $root['prefix'] . $args['message'];
                    }
                ],
            ],
        ]));
    }
}
```

The interesting piece here is `resolve` option of field definition. It is responsible for retuning 
value for our field. **Scalar** values will be directly included in response while **complex object** 
values will be passed down to nested field resolvers (not in this example though).

Field resolvers is the main mechanism of **graphql-php** to bind type system with your 
underlying data source.

Now when our type is ready, let's create GraphQL endpoint for it `graphql.php`:

```php
<?php
use GraphQL\GraphQL;
use GraphQL\Schema;

$schema = new Schema([
    'query' => $queryType, // or new MyApp\Type\QueryType()
]);

$rawInput = file_get_contents('php://input');

try {
    $rootValue = ['prefix' => 'You said: '];
    $result = GraphQL::execute($schema, $rawInput, $rootValue);
} catch (\Exception $e) {
    $result = [
        'error' => [
            'message' => $e->getMessage()
        ]
    ];
}
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($result);
```

Our example is ready. Try it by running:
```sh
php -S localhost:8000 graphql.php
curl http://localhost:8000 -d "query { echo(message: \"Hello World\") }"
```

Or grab the full [source code](https://github.com/webonyx/graphql-php/blob/master/examples/00-hello-world).
Obviously hello world only scratches the surface of what is possible.
So check out next example, which is closer to real-world apps.
 
Or just keep reading about [type system](types/) definitions.

# Blog example
It is often easier to start with full-featured example and then get back to documentation
for your own work. 

Check out [Blog example of GraphQL API](https://github.com/webonyx/graphql-php/tree/master/examples/01-blog).
It is quite close to real-world GraphQL hierarchies. Follow instructions and try it yourself in ~10 minutes.
