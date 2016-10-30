# About GraphQL

GraphQL is a modern way to build HTTP APIs consumed by web and mobile clients.
It is intended to be a replacement for REST and SOAP APIs (even for **existing applications**).

GraphQL itself is a [specification](https://github.com/facebook/graphql) designed by Facebook
engineers. Various implementations of this specification were written 
[for different languages and environments](http://graphql.org/code/).

Great overview of GraphQL features and benefits is presented on [official website](http://graphql.org/). 
All of them equally apply to this PHP implementation. 


# About graphql-php

**graphql-php** is an implementation of GraphQL specification in PHP (5.4+, 7.0+). 
It is based on [JavaScript implementation](https://github.com/graphql/graphql-js) 
published by Facebook as a reference for others.

This library is a thin wrapper around your existing data layer and business logic. 
It doesn't dictate how these layers are implemented or which storage engines 
are used. Instead it provides tools for creating rich API for your existing app. 

These tools include:

 - Primitives to express your app as a Type System
 - Tools for validation and introspection of this Type System
 - Tools for parsing, validating and executing GraphQL queries against this Type System
 - Rich error reporting

## Usage Example
```php
use GraphQL\GraphQL;
use GraphQL\Schema;

$query = '
{
  hero {
    id
    name
    friends {
      name
    }
  }
}
';

$schema = new Schema([
    // ...
    // Type System definition for your app goes here
    // ...
]);

$result = GraphQL::execute($schema, $query);
```

Result returned:
```php
[
  'hero' => [
    'id' => '2001',
    'name' => 'R2-D2',
    'friends' => [
      ['name' => 'Luke Skywalker'],
      ['name' => 'Han Solo'],
      ['name' => 'Leia Organa'],
    ]
  ]
]
```

Also check out full [Type System](https://github.com/webonyx/graphql-php/blob/master/tests/StarWarsSchema.php)
and [data source](https://github.com/webonyx/graphql-php/blob/master/tests/StarWarsData.php)
of this example.

## Current Status
Current version supports all features described by GraphQL specification 
(including April 2016 add-ons) as well as some experimental features like 
Schema Language parser.

Ready for real-world usage.