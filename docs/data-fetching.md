# Overview
GraphQL is data-storage agnostic. You can use any underlying data storage engine, including SQL or NoSQL database, 
plain files or in-memory data structures.

In order to convert GraphQL query to PHP array **graphql-php** traverses query fields (using depth-first algorithm) and 
runs special `resolve` function on each field. This `resolve` function is provided by you as a part of 
[field definition](type-system/object-types/#field-configuration-options).

Result returned by `resolve` function is directly included in response (for scalars and enums)
or passed down to nested fields (for objects).

Let's walk through an example. Consider following GraphQL query:

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

We need Schema that can fulfill it. On the very top level Schema contains Query type:

```php
$queryType = new ObjectType([
  'name' => 'Query',
  'fields' => [
  
    'lastStory' => [
      'type' => $blogStoryType,
      'resolve' => function() {
        return [
          'id' => 1,
          'title' => 'Example blog post',
          'authorId' => 1
        ];
      }
    ]
    
  ]
]);
```

As we see field `lastStory` has `resolve` function that is responsible for fetching data.

In our example we simply return array value, but in real-world application you would query
your database/cache/search index and return result.

Since `lastStory` is of complex type `BlogStory` this result is passed down to fields of this type:

```php
$blogStoryType = new ObjectType([
  'name' => 'BlogStory',
  'fields' => [

    'author' => [
      'type' => $userType,
      'resolve' => function($blogStory) {
        $users = [
          1 => [
            'id' => 1,
            'name' => 'Smith'
          ],
          2 => [
            'id' => 2,
            'name' => 'Anderson'
          ]
        ];
        return $users[$blogStory['authorId']];
      }
    ],
    
    'title' => [
      'type' => Type::string()
    ]

  ]
]);
```

Here `$blogStory` is the array returned by `lastStory` field above. 

Again: in real-world applications you would fetch user data from datastore by `authorId` and return it.
Also note that you don't have to return arrays. You can return any value, **graphql-php** will pass it untouched
to nested resolvers.

But then the question appears - field `title` has no `resolve` option. How is it resolved?

The answer is: there is default resolver for all fields. When you define your own `resolve` function
for a field you simply override this default resolver.

# Default Field Resolver
**graphql-php** provides following default field resolver:
```php
function defaultFieldResolver($source, $args, $context, ResolveInfo $info)
{
    $fieldName = $info->fieldName;
    $property = null;

    if (is_array($source) || $source instanceof \ArrayAccess) {
        if (isset($source[$fieldName])) {
            $property = $source[$fieldName];
        }
    } else if (is_object($source)) {
        if (isset($source->{$fieldName})) {
            $property = $source->{$fieldName};
        }
    }

    return $property instanceof \Closure ? $property($source, $args, $context) : $property;
}
```

As you see it returns value by key (for arrays) or property (for objects). If value is not set - it returns `null`.

To override default resolver - use:
```php
GraphQL\GraphQL::setDefaultFieldResolver($myResolverCallback);
```

# Default Field Resolver per Type
Sometimes it might be convenient to set default field resolver per type. You can do so by providing
[resolveField option in type config](type-system/object-types/#configuration-options). For example:

```php
$userType = new ObjectType([
  'name' => 'User',
  'fields' => [

    'name' => Type::string(),
    'email' => Type::string()

  ],
  'resolveField' => function(User $user, $args, $context, ResolveInfo $info) {
    switch ($info->fieldName) {
        case 'name':
          return $user->getName();
        case 'email':
          return $user->getEmail();
        default:
          return null;
    }
  }
]);
```

Keep in mind that **field resolver** has precedence over **default field resolver per type** which in turn
 has precedence over **default field resolver**.


# Solving N+1 Problem
Since: 9.0

One of the most annoying problems with data fetching is so-called [N+1 problem](https://secure.phabricator.com/book/phabcontrib/article/n_plus_one/).

Consider following GraphQL query:
```
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

Naive field resolution process would require up to 10 calls to underlying data store to fetch authors for all 10 stories.

**graphql-php** provides tools to mitigate this problem: it allows you to defer actual field resolution to later stage 
when one batched query could be executed instead of 10 distinct queries.

Here is an example of `BlogStory` resolver for field `author` that uses deferring:
```php
'resolve' => function($blogStory) {
    MyUserBuffer::add($blogStory['authorId']);

    return new GraphQL\Deferred(function () use ($blogStory) {
        MyUserBuffer::loadOnce();
        return MyUserBuffer::get($blogStory['authorId']);
    });
}
```

In this example we fill up buffer with 10 author ids first. Then **graphql-php** continues 
resolving other non-deferred fields until there are none of them left.

After that it calls `Closures` wrapped by `GraphQL\Deferred` which in turn load all buffered 
ids once (using SQL IN(?), Redis MGET or other similar tools) and return final field value.

Originally this approach was advocated by Facebook in their [Dataloader](https://github.com/facebook/dataloader)
project.

This solution enables very interesting optimizations at no cost. Consider following query:

```
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

Even if `author` field is located on different levels of query - it can be buffered in the same buffer.
In this example only one query will be executed for all story authors comparing to 20 queries
in naive implementation.

# Async PHP
Since: 9.0

If your project runs in environment that supports async operations 
(like `HHVM`, `ReactPHP`, `Icicle.io`, `appserver.io` `PHP threads`, etc) you can leverage
the power of your platform to resolve fields asynchronously.

The only requirement: your platform must support the concept of Promises compatible with
[Promises A+](https://promisesaplus.com/) specification.

To enable async support - set adapter for promises:
```
GraphQL\GraphQL::setPromiseAdapter($adapter);
```

Where `$adapter` is an instance of class implementing `GraphQL\Executor\Promise\PromiseAdapter` interface.

Then in your `resolve` functions you should return `Promises` of your platform instead of 
`GraphQL\Deferred` instances.

Platforms supported out of the box:

* [ReactPHP](https://github.com/reactphp/react) (requires **react/promise** as composer dependency):
  `GraphQL\GraphQL::setPromiseAdapter(new GraphQL\Executor\Promise\Adapter\ReactPromiseAdapter());`
* HHVM: TODO

To integrate other platform - implement `GraphQL\Executor\Promise\PromiseAdapter` interface. 
