# Schema Definition
Schema is a container of your type hierarchy, which accepts root types in constructor and provides
methods for receiving information about your types to internal GrahpQL tools.

In **graphql-php** schema is an instance of [`GraphQL\Type\Schema`](/reference/#graphqltypeschema) 
which accepts configuration array in constructor:

```php
use GraphQL\Type\Schema;

$schema = new Schema([
    'query' => $queryType, 
    'mutation' => $mutationType,
]);
```
See possible constructor options [below](#configuration-options).

# Query and Mutation types
Schema consists of two root types:
 
* `Query` type is a surface of your read API
* `Mutation` type (optional) exposes write API by declaring all possible mutations in your app. 

Query and Mutation types are regular [object types](object-types/) containing root-level fields 
of your API:

```php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'hello' => [
            'type' => Type::string(),
            'resolve' => function() {
                return 'Hello World!';
            }
        ]
        'hero' => [
            'type' => $characterInterface,
            'args' => [
                'episode' => [
                    'type' => $episodeEnum
                ]
            ],
            'resolve' => function ($rootValue, $args) {
                return StarWarsData::getHero(isset($args['episode']) ? $args['episode'] : null);
            },
        ]
    ]
]);

$mutationType = new ObjectType([
    'name' => 'Mutation',
    'fields' => [
        'createReviewForEpisode' => [
            'type' => $createReviewForEpisodeMutation,
            'args' => [
                'episode' => $episodeEnum,
                'review' => $reviewInputObject
            ],
            'resolve' => function($val, $args) {
                // TODOC
            }
        ]
    ]
]);
```

Keep in mind that other than the special meaning of declaring surface area of your API, 
those types are the same as any other [object type](object-types/), and their fields work 
exactly the same way.

**Mutation** type is also just a regular object type. The difference is in semantics. 
Field names of Mutation type are usually verbs and they almost always have arguments - quite often 
with complex input values (see [Input Types](input-types/) for details).

# Configuration Options
Schema constructor expects an instance of [`GraphQL\Type\SchemaConfig`](/reference/#graphqltypeschemaconfig) 
or an array with following options:

Option       | Type     | Notes
------------ | -------- | -----
query        | `ObjectType` | **Required.** Object type (usually named "Query") containing root-level fields of your read API
mutation     | `ObjectType` | Object type (usually named "Mutation") containing root-level fields of your write API
subscription     | `ObjectType` | Reserved for future subscriptions implementation. Currently presented for compatibility with introspection query of **graphql-js**, used by various clients (like Relay or GraphiQL)
directives  | `Directive[]` | Full list of [directives](directives/) supported by your schema. By default contains built-in `@skip` and `@include` directives.<br><br> If you pass your own directives and still want to use built-in directives - add them explicitly. For example: `array_merge(GraphQL::getInternalDirectives(), [$myCustomDirective]`
types     | `ObjectType[]` | List of object types which cannot be detected by **graphql-php** during static schema analysis.<br><br>Most often it happens when object type is never referenced in fields directly, but is still a part of schema because it implements an interface which resolves to this object type in it's `resolveType` callback. <br><br> Note that you are not required to pass all of your types here - it is simply a workaround for concrete use-case.

# Lazy loading of types
By default a schema will scan all of your type and field definitions to serve GraphQL queries. 
It may cause performance overhead when there are many types in a schema. 

In this case it is recommended to pass **typeLoader** option to schema constructor and define all 
of your object **fields** as callbacks.

Type loading concept is very similar to PHP class loading, but keep in mind that **typeLoader** must
always return the same instance of a type.

Usage example:
```php
class Types
{
    private $registry = [];

    public function get($name)
    {
        if (!isset($this->types[$name])) {
            $this->types[$name] = $this->{$name}();
        }
        return $this->types[$name];
    }

    private function MyTypeA()
    {
        return new ObjectType([
            'name' => 'MyTypeA',
            'fields' => function() {
                return [
                    'b' => ['type' => $this->get('MyTypeB')]
                ];
            }
        ]);
    }
    
    private function MyTypeB()
    {
        // ...
    }
}

$registry = new Types();

$schema = new Schema([
    'query' => $registry->get('Query'),
    'typeLoader' => function($name) use ($registry) {
        return $registry->get($name);
    }
]);
```


# Schema Validation
By default schema is created with only shallow validation of type and field definitions  
(because validation requires full schema scan and is very costly on bigger schemas).

But there is a special method `$schema->assertValid()` which throws `GraphQL\Error\InvariantViolation` 
exception when it encounters any error, like:

- Invalid types used for fields / arguments
- Missing interface implementations
- Invalid interface implementations
- Other schema errors...

Schema validation is supposed to be used in CLI commands or during build step of your app.
Don't call it in web requests in production. 

Usage example:
```php
$schema = new GraphQL\Type\Schema([
    'query' => $myQueryType
]);

try {
    $schema->assertValid();
} catch (GraphQL\Error\InvariantViolation $e) {
    echo $e->getMessage();
}
```
