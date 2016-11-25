# graphql-php

This is a PHP port of GraphQL reference implementation based on the [specification](https://github.com/facebook/graphql)
and the [reference implementation in JavaScript](https://github.com/graphql/graphql-js).

This implementation will follow JavaScript version as close as possible until GraphQL itself stabilizes.

**Current status**: version 0.4+ supports all features described by specification.

[![Build Status](https://travis-ci.org/webonyx/graphql-php.svg?branch=master)](https://travis-ci.org/webonyx/graphql-php)
[![Coverage Status](https://coveralls.io/repos/github/webonyx/graphql-php/badge.svg)](https://coveralls.io/github/webonyx/graphql-php)
[![Latest Stable Version](https://poser.pugx.org/webonyx/graphql-php/version)](https://packagist.org/packages/webonyx/graphql-php)
[![License](https://poser.pugx.org/webonyx/graphql-php/license)](https://packagist.org/packages/webonyx/graphql-php)

Work is in progress on new [Documentation site](http://webonyx.github.io/graphql-php/). It already
contains more information than this Readme, so try it first.

## Table of Contents

- [Overview](#overview)
- [Installation](#installing-graphql-php)
- [Learn by example](#learn-by-example)
- [Type System](#type-system)
    - [Internal Types](#internal-types)
    - [Enums](#enums)
    - [Interfaces](#interfaces)
    - [Objects](#objects)
    - Unions (TODOC)
    - [Fields](#fields)
- [Schema definition](#schema)
- [Query Resolution and Data Fetching](#query-resolution)
- [HTTP endpoint example](#http-endpoint)
- [More Examples](#more-examples)
- [Complementary Tools](#complementary-tools)

## Overview
GraphQL is intended to be a replacement for REST APIs. [Read more](http://facebook.github.io/react/blog/2015/05/01/graphql-introduction.html) about rationale behind it.

Example usage:
```php
$result = GraphQL::execute(
  StarWarsSchema::build(),
  'query HeroNameAndFriendsQuery {
    hero {
      id
      name
      friends {
        name
      }
    }
  }'
)
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
(see also [schema definition](https://github.com/webonyx/graphql-php/blob/master/tests/StarWarsSchema.php#L22) for type system of this example).

This PHP implementation is a thin wrapper around your existing data layer and business logic. It doesn't dictate how these layers are implemented or which storage engines are used. Instead it provides tools for creating API for your existing app. These tools include:
- Type system
- Schema validation and introspection
- Ability to parse and execute GraphQL queries against type system

Actual data fetching has to be implemented on the user land. 

Check out single-file [hello world](https://gist.github.com/leocavalcante/9e61ca6065130e37737f24892d81fa40) example for quick introduction.

## Installing graphql-php
```
$> curl -sS https://getcomposer.org/installer | php
$> php composer.phar require webonyx/graphql-php="^0.8"
```

If you are upgrading, see [upgrade instructions](UPGRADE.md)

## Requirements
PHP >=5.4

## Learn by example
It is often easier to start with full-featured example and then get back to documentation
for your own work. 

Check out full-featured [example of GraphQL API](https://github.com/webonyx/graphql-php/tree/master/examples/01-blog).
Follow instructions and try it yourself in ~10 minutes.

## Getting Started
First, make sure to read [Getting Started](https://github.com/facebook/graphql#getting-started) section of GraphQL documentation.
Examples below implement the type system described in this document.

### Type System
To start using GraphQL you are expected to implement a Type system.

GraphQL PHP provides several *kinds* of types to build a hierarchical type system:
 `scalar`, `enum`, `object`, `interface`, `union`, `listOf`, `nonNull`.

#### Internal types
Only several `scalar` types are implemented out of the box:
`ID`, `String`, `Int`, `Float`, `Boolean`

As well as two internal modifier types: `ListOf` and `NonNull`.

All internal types are exposed as static methods of `GraphQL\Type\Definition\Type` class:

```php
use GraphQL\Type\Definition\Type;

// Internal Scalar types:
Type::string();  // String type
Type::int();     // Int type
Type::float();   // Float type
Type::boolean(); // Boolean type
Type::id();      // ID type

// Internal wrapping types:
Type::nonNull(Type::string()) // String! type
Type::listOf(Type::string())  // String[] type
```

Other types must be implemented by your application. Most often you will work with `enum`, `object`, `interface` and `union` type *kinds* to build a type system.

#### Enums
Enum types represent a set of allowed values for an object field. Let's define `enum` type describing the set of episodes of original Star Wars trilogy:

```php
use GraphQL\Type\Definition\EnumType;

/**
 * The original trilogy consists of three movies.
 *
 * This implements the following type system shorthand:
 *   enum Episode { NEWHOPE, EMPIRE, JEDI }
 */
$episodeEnum = new EnumType([
    'name' => 'Episode',
    'description' => 'One of the films in the Star Wars Trilogy',
    'values' => [
        'NEWHOPE' => [
            'value' => 4,
            'description' => 'Released in 1977.'
        ],
        'EMPIRE' => [
            'value' => 5,
            'description' => 'Released in 1980.'
        ],
        'JEDI' => [
            'value' => 6,
            'description' => 'Released in 1983.'
        ],
    ]
]);
```

#### Interfaces
Next, let's define a `Character` interface, describing characters of original Star Wars trilogy:

```php
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

// Implementor types (will be defined in next examples):
$humanType = null;
$droidType = null;

/**
 * Characters in the Star Wars trilogy are either humans or droids.
 *
 * This implements the following type system shorthand:
 *   interface Character {
 *     id: String!
 *     name: String
 *     friends: [Character]
 *     appearsIn: [Episode]
 *   }
 */
$characterInterface = new InterfaceType([
    'name' => 'Character',
    'description' => 'A character in the Star Wars Trilogy',
    'fields' => [
        'id' => [
            'type' => Type::nonNull(Type::string()),
            'description' => 'The id of the character.',
        ],
        'name' => [
            'type' => Type::string(),
            'description' => 'The name of the character.'
        ],
        'friends' => [
            'type' => function () use (&$characterInterface) {
                return Type::listOf($characterInterface);
            },
            'description' => 'The friends of the character.',
        ],
        'appearsIn' => [
            'type' => Type::listOf($episodeEnum),
            'description' => 'Which movies they appear in.'
        ]
    ],
    'resolveType' => function ($obj) use (&$humanType, &$droidType) {
        $humans = StarWarsData::humans();
        $droids = StarWarsData::droids();
        if (isset($humans[$obj['id']])) {
            return $humanType;
        }
        if (isset($droids[$obj['id']])) {
            return $droidType;
        }
        return null;
    },
]);
```

As you can see `type` may be optionally defined as `callback` that returns actual type at runtime. (see [Fields](#fields) section for details)

In this example field `friends` represents a list of `characterInterface`. Since at the moment of type definition `characterInterface` is still not defined, we pass in `closure` that will return this type at runtime.

**Interface definition options:**

Option | Type | Notes
------ | ---- | -----
name | `string` | Required. Unique name of this interface type within Schema
fields | `array` | Required. List of fields required to be defined by interface implementors. See [Fields](#fields) section for available options.
description | `string` | Textual description of this interface for clients
resolveType | `callback($value, $context, ResolveInfo $info) => objectType` | Any `callable` that receives data from data layer of your application and returns concrete interface implementor for that data.


**Notes**:

1. If `resolveType` option is omitted, GraphQL PHP will loop through all interface implementors and use their `isTypeOf()` method to pick the first suitable one. This is obviously less efficient than single `resolveType` call. So it is recommended to define `resolveType` when possible.

2. Interface types do not participate in data fetching. They just resolve actual `object` type which will be asked for data when GraphQL query is executed.


#### Objects
Now let's define `Human` type that implements `CharacterInterface` from example above:

```php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * We define our human type, which implements the character interface.
 *
 * This implements the following type system shorthand:
 *   type Human : Character {
 *     id: String!
 *     name: String
 *     friends: [Character]
 *     appearsIn: [Episode]
 *   }
 */
$humanType = new ObjectType([
    'name' => 'Human',
    'description' => 'A humanoid creature in the Star Wars universe.',
    'fields' => [
        'id' => [
            'type' => Type::nonNull(Type::string()),
            'description' => 'The id of the human.',
        ],
        'name' => [
            'type' => Type::string(),
            'description' => 'The name of the human.',
        ],
        'friends' => [
            'type' => Type::listOf($characterInterface),
            'description' => 'The friends of the human',
            'resolve' => function ($human) {
                return StarWarsData::getFriends($human);
            },
        ],
        'appearsIn' => [
            'type' => Type::listOf($episodeEnum),
            'description' => 'Which movies they appear in.'
        ],
        'homePlanet' => [
            'type' => Type::string(),
            'description' => 'The home planet of the human, or null if unknown.'
        ],
    ],
    'interfaces' => [$characterInterface]
]);
```

**Object definition options**

Option | Type | Notes
------ | ---- | -----
name | `string` | Required. Unique name of this object type within Schema
fields | `array` | Required. List of fields describing object properties. See [Fields](#fields) section for available options.
description | `string` | Textual description of this type for clients
interfaces | `array` or `callback() => ObjectType[]` | List of interfaces implemented by this type (or callback returning list of interfaces)
isTypeOf | `callback($value, $context, GraphQL\Type\Definition\ResolveInfo $info)` | Callback that takes `$value` provided by your data layer and returns true if that `$value` qualifies for this type

**Notes:**

1. Both `object` types and `interface` types define set of fields which can have their own types. That's how type composition is implemented.

2. Object types are responsible for data fetching. Each of their fields may have optional `resolve` callback option. This callback takes `$value` that corresponds to instance of this type and returns `data` accepted by type of given field.
If `resolve` option is not set, GraphQL will try to get `data` from `$value[$fieldName]`.

3. `resolve` callback is a place where you can use your existing data fetching logic. `$context` is defined by your application on the top level of query execution (useful for storing current user, environment details, etc)

4. Other `ObjectType` mentioned in examples is `Droid`. Check out tests for this type: https://github.com/webonyx/graphql-php/blob/master/tests/StarWarsSchema.php


#### Unions
TODOC

#### Fields

Fields are parts of [Object](#objects) and [Interface](#interfaces) type definitions.

Allowed Field definition options:

Option | Type | Notes
------ | ---- | -----
name | `string` | Required. Name of the field. If not set - GraphQL will look use `key` of fields array on type definition.
type | `Type` or `callback() => Type` | Required. One of internal or custom types. Alternatively - callback that returns `type`.
args | `array` | Array of possible type arguments. Each entry is expected to be an array with following keys: **name** (`string`), **type** (`Type` or `callback() => Type`), **defaultValue** (`any`)
resolve | `callback($value, $args, $context, ResolveInfo $info) => $fieldValue` | Function that receives `$value` of parent type and returns value for this field. `$context` is also defined by your application in the root call to `GraphQL::execute()`
description | `string` | Field description for clients
deprecationReason | `string` | Text describing why this field is deprecated. When not empty - field will not be returned by introspection queries (unless forced)


### Schema
After all of your types are defined, you must define schema. Schema consists of two special root-level types: `Query` and `Mutation`

`Query` type is a surface of your *read* API. `Mutation` type exposes *write* API by declaring all possible mutations in your app.

Example schema:
```php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Schema;

/**
 * This is the type that will be the root of our query, and the
 * entry point into our schema. It gives us the ability to fetch
 * objects by their IDs, as well as to fetch the undisputed hero
 * of the Star Wars trilogy, R2-D2, directly.
 *
 * This implements the following type system shorthand:
 *   type Query {
 *     hero(episode: Episode): Character
 *     human(id: String!): Human
 *     droid(id: String!): Droid
 *   }
 *
 */
$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'hero' => [
            'type' => $characterInterface,
            'args' => [
                'episode' => [
                    'description' => 'If omitted, returns the hero of the whole saga. If provided, returns the hero of that particular episode.',
                    'type' => $episodeEnum
                ]
            ],
            'resolve' => function ($root, $args) {
                return StarWarsData::getHero(isset($args['episode']) ? $args['episode'] : null);
            },
        ],
        'human' => [
            'type' => $humanType,
            'args' => [
                'id' => [
                    'name' => 'id',
                    'description' => 'id of the human',
                    'type' => Type::nonNull(Type::string())
                ]
            ],
            'resolve' => function ($root, $args) {
                $humans = StarWarsData::humans();
                return isset($humans[$args['id']]) ? $humans[$args['id']] : null;
            }
        ],
        'droid' => [
            'type' => $droidType,
            'args' => [
                'id' => [
                    'name' => 'id',
                    'description' => 'id of the droid',
                    'type' => Type::nonNull(Type::string())
                ]
            ],
            'resolve' => function ($root, $args) {
                $droids = StarWarsData::droids();
                return isset($droids[$args['id']]) ? $droids[$args['id']] : null;
            }
        ]
    ]
]);

// TODOC
$mutationType = null;

$schema = new Schema([
    'query' => $queryType, 
    'mutation' => $mutationType,
    
    // We need to pass the types that implement interfaces in case the types are only created on demand.
    // This ensures that they are available during query validation phase for interfaces. 
    'types' => [
        $humanType,
        $droidType
    ]
]);
```

**Notes:**

1. `Query` is a regular `object` type.

2. Fields of this type represent all possible root-level queries to your API.

3. Fields can have `args`, so that your queries could be dynamic (see [Fields](#fields) section).


### Query Resolution
Resolution is a cascading process that starts from root `Query` type.

In our example `Query` type exposes field `human` that expects `id` argument. Say we receive following GraphQL query that requests data for Luke Skywalker:
```
query FetchLukeQuery {
  human(id: "1000") {
    name
    friends {
      name
    }
  }
}
```

And that's how our data for Luke looks like (in some internal storage):
```
$lukeData = [
    'id' => '1000',
    'name' => 'Luke Skywalker',
    'friends' => ['1002', '1003', '2000', '2001'],
    'appearsIn' => [4, 5, 6],
    'homePlanet' => 'Tatooine',
]
```

What happens:

1. GraphQL query is parsed and validated against schema (it happens in `GraphQL\GraphQL::execute()` method)
2. GraphQL executor detects that field `human` of `Human` type is requested at root `Query` level
3. It calls `resolve(null, ['id' => 1000])` on this field (note first argument is null at the root level)
4. `resolve` callback of `human` field fetches our data by id and returns it
5. Since field `human` is expected to return type `Human` GraphQL traverses all requested fields of type `Human` and matches them against `$lukeData`
6. Requested field `name` on `Human` type does not provide any `resolve` callback, so GraphQL simply resolves it as `$lukeData['name']`
7. Requested field `friend` has `resolve` callback, so it is called: `resolve($lukeData, /*args*/ [], ResolveInfo $info)`
8. Callback fetches data for all `$lukeData['friends']` and returns `[$friend1002, $friend1003, ...]` where each entry contains array with same structure as `$lukeData`
9. GraphQL executor repeats these steps until all requested leaf fields are reached
10. Final result is composed and returned:
```
[
    'human' => [
        'name' => 'Luke Skywalker',
        'friends' => [
            ['name' => 'Han Solo'],
            ['name' => 'Leia Organa'],
            ['name' => 'C-3PO'],
            ['name' => 'R2-D2'],
        ]
    ]
]
```

### HTTP endpoint
Specification for GraphQL HTTP endpoint is still under development.
But you can use following naive example to build your own custom HTTP endpoint that is ready to accept GraphQL queries:

```php
use GraphQL\GraphQL;
use \Exception;

if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] === 'application/json') {
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody ?: '', true);
} else {
    $data = $_POST;
}

$requestString = isset($data['query']) ? $data['query'] : null;
$operationName = isset($data['operation']) ? $data['operation'] : null;
$variableValues = isset($data['variables']) ? $data['variables'] : null;

try {
    // Define your schema:
    $schema = MyApp\Schema::build();
    $result = GraphQL::execute(
        $schema,
        $requestString,
        /* $rootValue */ null,
        /* $context */ null, // A custom context that can be used to pass current User object etc to all resolvers.
        $variableValues,
        $operationName
    );
} catch (Exception $exception) {
    $result = [
        'errors' => [
            ['message' => $exception->getMessage()]
        ]
    ];
}

header('Content-Type: application/json');
echo json_encode($result);
```

### Security

#### Query Complexity Analysis

This is a PHP port of [Query Complexity Analysis](http://sangria-graphql.org/learn/#query-complexity-analysis) in Sangria implementation.
Introspection query with description max complexity is **109**.

This document validator rule is disabled by default. Here an example to enabled it:

```php
use GraphQL\GraphQL;

/** @var \GraphQL\Validator\Rules\QueryComplexity $queryComplexity */
$queryComplexity = DocumentValidator::getRule('QueryComplexity');
$queryComplexity->setMaxQueryComplexity($maxQueryComplexity = 110);

GraphQL::execute(/*...*/);
```

#### Limiting Query Depth

This is a PHP port of [Limiting Query Depth](http://sangria-graphql.org/learn/#limiting-query-depth) in Sangria implementation.
Introspection query with description max depth is **7**.

This document validator rule is disabled by default. Here an example to enabled it:

```php
use GraphQL\GraphQL;

/** @var \GraphQL\Validator\Rules\QueryDepth $queryDepth */
$queryDepth = DocumentValidator::getRule('QueryDepth');
$queryDepth->setMaxQueryDepth($maxQueryDepth = 10);

GraphQL::execute(/*...*/);
```

### More Examples
Make sure to check [tests](https://github.com/webonyx/graphql-php/tree/master/tests) for more usage examples.

### Complementary Tools
- [Integration with Relay](https://github.com/ivome/graphql-relay-php)
- [Use GraphQL with Laravel 5](https://github.com/Folkloreatelier/laravel-graphql)
- [Relay helpers for laravel-graphql](https://github.com/nuwave/laravel-graphql-relay)
- [GraphQL and Relay with Symfony2](https://github.com/overblog/GraphQLBundle)

Also check [Awesome GraphQL](https://github.com/chentsulin/awesome-graphql) for full picture of GraphQL ecosystem.
