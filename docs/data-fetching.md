## Overview

GraphQL is data-storage agnostic. You can use any underlying data storage engine, including but not limited to
SQL or NoSQL databases, plain files or in-memory data structures.

In order to convert the GraphQL query to a PHP array, **graphql-php** traverses query fields (using depth-first algorithm)
and runs the special **resolve** function on each field. This **resolve** function is provided by you as a part of the
[field definition](type-definitions/object-types.md#field-configuration-options) or [query execution call](executing-queries.md).

The result returned by the **resolve** function is directly included in the response (for scalars and enums)
or passed down to nested fields (for objects).

Let's walk through an example. Consider the following GraphQL query:

```graphql
{
  lastStory {
    title
    author {
      name
    }
  }
}
```

We need a `Schema` that can fulfill it. On the very top level, the `Schema` contains the `Query` type:

```php
use GraphQL\Type\Definition\ObjectType;

$queryType = new ObjectType([
    'name' => 'Query',
        'fields' => [
        'lastStory' => [
            'type' => $blogStoryType,
            'resolve' => fn (): array => [
                'id' => 1,
                'title' => 'Example blog post',
                'authorId' => 1
            ],
        ]
    ]
]);
```

As we see, the field **lastStory** has a **resolve** function that is responsible for fetching data.

In our example, we simply return a static value, but in the real-world application you would query
your data source and return the result from there.

Since **lastStory** is of composite type **BlogStory**, this result is passed down to fields of this type:

```php
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;

const USERS = [
    1 => [
        'id' => 1,
        'name' => 'Smith'
    ],
    2 => [
        'id' => 2,
        'name' => 'Anderson'
    ]
];

$blogStoryType = new ObjectType([
    'name' => 'BlogStory',
    'fields' => [
        'author' => [
            'type' => $userType,
            'resolve' => fn (array $blogStory): array => USERS[$blogStory['authorId']],
        ],
        'title' => [
            'type' => Type::string()
        ]
    ]
]);
```

Here **$blogStory** is the array returned by the **lastStory** field above.

Again: in real-world applications you would fetch user data from your data source by **authorId** and return it.
Also, note that you don't have to return arrays. You can return any value, **graphql-php** will pass it untouched
to nested resolvers.

But then the question appears - the field **title** has no **resolve** option, how is it resolved?
When you define no custom resolver, [the default field resolver](#default-field-resolver) applies.

## Default Field Resolver

**graphql-php** provides the following default field resolver:

```php
use GraphQL\Type\Definition\ResolveInfo;

function defaultFieldResolver($objectValue, array $args, $context, ResolveInfo $info)
{
    $fieldName = $info->fieldName;
    $property = null;

    if (is_array($objectValue) || $objectValue instanceof ArrayAccess) {
        if (isset($objectValue[$fieldName])) {
            $property = $objectValue[$fieldName];
        }
    } elseif (is_object($objectValue)) {
        if (isset($objectValue->{$fieldName})) {
            $property = $objectValue->{$fieldName};
        }
    }

    return $property instanceof Closure
        ? $property($objectValue, $args, $contextValue, $info)
        : $property;
}
```

It returns value by key (for arrays) or property (for objects).
If the value is not set, it returns **null**.

To override the default resolver, pass it as an argument to [executeQuery](executing-queries.md).

## Default Field Resolver per Type

Sometimes it might be convenient to set a default field resolver per type.
You can do so by providing [the **resolveField** option in the type config](type-definitions/object-types.md#configuration-options).
For example:

```php
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

$userType = new ObjectType([
    'name' => 'User',
    'fields' => [
        'name' => Type::string(),
        'email' => Type::string()
    ],
    'resolveField' => function (User $user, array $args, $context, ResolveInfo $info): ?string {
        switch ($info->fieldName) {
            case 'name': return $user->getName();
            case 'email': return $user->getEmail();
            default: return null;
        }
    },
]);
```

Keep in mind that **field resolver** has precedence over **default field resolver per type** which in turn
has precedence over **default field resolver**.

## Optimize Resolvers

The 4th argument of **resolver** functions is an instance of [ResolveInfo](./class-reference.md#graphqltypedefinitionresolveinfo).
It contains information that is useful for the field resolution process.

Depending on which data source is used, knowing which fields the client queried can be used to optimize
the performance of a resolver. For example, an SQL query may only need to select the queried fields.

The following example limits which columns are selected from the database:

```php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'lastStory' => [
            'type' => $storyType,
            'resolve' => function ($root, array $args, $context, ResolveInfo $resolveInfo): Story {
                // Fictitious API, use whatever database access your application/framework provides
                $builder = Story::builder();
                foreach ($resolveInfo->getFieldSelection() as $field => $_) {
                    $builder->addSelect($field);
                }

                return $builder->last();
            }
        ]
    ]
]);
```

## Solving N+1 Problem

Since: 0.9.0

One of the most annoying problems with data fetching is a so-called
[N+1 problem](https://secure.phabricator.com/book/phabcontrib/article/n_plus_one/). <br>
Consider following GraphQL query:

```graphql
{
  topStories(limit: 10) {
    title
    author {
      name
      email
    }
  }
}
```

Naive field resolution process would require up to 10 calls to the underlying data store to fetch authors for all 10 stories.

**graphql-php** provides tools to mitigate this problem: it allows you to defer actual field resolution to a later stage
when one batched query could be executed instead of 10 distinct queries.

Here is an example of **BlogStory** resolver for field **author** that uses deferring:

```php
use GraphQL\Deferred;

'resolve' => function (array $blogStory): Deferred {
    MyUserBuffer::add($blogStory['authorId']);

    return new Deferred(function () use ($blogStory): User {
        MyUserBuffer::loadBuffered();

        return MyUserBuffer::get($blogStory['authorId']);
    });
}
```

In this example, we fill up the buffer with 10 author ids first. Then **graphql-php** continues
resolving other non-deferred fields until there are none of them left.

After that, it calls closures wrapped by `GraphQL\Deferred` which in turn load all buffered
ids once (using SQL `IN(?)`, Redis `MGET` or similar tools) and returns the final field value.

Originally this approach was advocated by Facebook in their [Dataloader](https://github.com/graphql/dataloader)
project. This solution enables very interesting optimizations at no cost. Consider the following query:

```graphql
{
  topStories(limit: 10) {
    author {
      email
    }
  }
  category {
    stories(limit: 10) {
      author {
        email
      }
    }
  }
}
```

Even though **author** field is located on different levels of the query - it can be buffered in the same buffer.
In this example, only one query will be executed for all story authors comparing to 20 queries
in a naive implementation.

## Async PHP

If your project runs in an environment that supports async operations
(like HHVM, ReactPHP, AMPHP, appserver.io, PHP threads, etc)
you can leverage the power of your platform to resolve some fields asynchronously.

The only requirement: your platform must support the concept of Promises compatible with
[Promises A+](https://promisesaplus.com/) specification.

To start using this feature, switch facade method for query execution from
**executeQuery** to **promiseToExecute**:

```php
use GraphQL\GraphQL;
use GraphQL\Executor\ExecutionResult;

$promise = GraphQL::promiseToExecute(
    $promiseAdapter,
    $schema,
    $queryString,
    $rootValue = null,
    $contextValue = null,
    $variableValues = null,
    $operationName = null,
    $fieldResolver = null,
    $validationRules = null
);
$promise->then(fn (ExecutionResult $result): array => $result->toArray());
```

Where **$promiseAdapter** is an instance of:

- For [ReactPHP](https://github.com/reactphp/react) (requires **react/promise** as composer dependency): <br>
  `GraphQL\Executor\Promise\Adapter\ReactPromiseAdapter`

- For [AMPHP](https://github.com/amphp/amp): <br>
  `GraphQL\Executor\Promise\Adapter\AmpPromiseAdapter`

- For [Swoole](https://swoole.com/) or [OpenSwoole](https://openswoole.com/): <br>
  You can use an external library: [Resonance](https://resonance.distantmagic.com/docs/features/graphql/standalone-promise-adapter.html)

- Other platforms: write your own class implementing interface: <br>
  [`GraphQL\Executor\Promise\PromiseAdapter`](class-reference.md#graphqlexecutorpromisepromiseadapter).

Then your **resolve** functions should return promises of your platform instead of `GraphQL\Deferred`s.
