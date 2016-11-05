# Object Type Definition
Object Type is the most frequently used primitive in typical GraphQL application.

Conceptually Object Type is a collection of Fields. Each field in turn
has it's own type which allows to build complex hierarchies.

In **graphql-php** object type is an instance of `GraphQL\Type\Definition\ObjectType` 
(or one of it subclasses) which accepts configuration array in constructor:

```php
<?php
namespace MyApp;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Examples\Blog\Data\DataSource;
use GraphQL\Examples\Blog\Data\Story;

$userType = new ObjectType([
    'name' => 'User',
    'description' => 'Our blog visitor',
    'fields' => [
        'firstName' => [
            'type' => Type::string(),
            'description' => 'User first name'
        ],
        'email' => Type::string()
    ]
]);

$blogStory = new ObjectType([
    'name' => 'Story',
    'fields' => [
        'body' => Type::string(),
        'author' => [
            'type' => $userType,
            'description' => 'Story author',
            'resolve' => function(Story $blogStory) {
                return DataSource::findUser($blogStory->authorId);
            }
        ],
        'likes' => [
            'type' => Type::listOf($userType),
            'description' => 'List of users who liked the story',
            'args' => [
                'limit' => [
                    'type' => Type::int(),
                    'description' => 'Limit the number of recent likes returned',
                    'defaultValue' => 10
                ]
            ],
            'resolve' => function(Story $blogStory, $args) {
                return DataSource::findLikes($blogStory->id, $args['limit']);
            }
        ]
    ]
]);
```
This example uses **inline** style for Object Type definitions, but there are also 
[other styles](/type-system/#type-definition-styles) (using inheritance or composition).


# Configuration options
Object type constructor expects configuration array. Below is a full list of available options:

Option       | Type     | Notes
------------ | -------- | -----
name         | `string` | **Required.** Unique name of this object type within Schema
fields       | `array` or `callback` returning `array` | **Required**. Array describing object fields. See [Fields](#field-definitions) section below for expected structure of each array entry. See also section on [Circular types](#) for explanation of when to use callback for this option.
description  | `string` | Plain-text description of this type for clients (e.g. used by [GraphiQL](https://github.com/graphql/graphiql) for auto-generated documentation)
interfaces   | `array` or `callback` returning `array` | List of interfaces implemented by this type. See [Interface Types](/type-system/interface-types) for details. See also section on [Circular types](#) for explanation of when to use callback for this option.
isTypeOf     | `callback` returning `boolean` | **function($value, $context, GraphQL\Type\Definition\ResolveInfo $info)** Expected to return `true` if `$value` qualifies for this type (see section about [Abstract Type Resolution](#) for explanation).
resolveField | `callback` returning `mixed` | **function($value, $args, $context, GraphQL\Type\Definition\ResolveInfo $info)** Given the `$value` of this type it is expected to return value for field defined in `$info->fieldName`. Good place to define type-specific strategy for field resolution. See section on [Data Fetching](#) for details.

# Field configuration options
Below is a full list of available field configuration options:

Option | Type | Notes
------ | ---- | -----
name | `string` | **Required.** Name of the field. When not set - inferred from array key (read about [shorthand field definition](#) below)
type | `Type` | **Required.** Instance of internal or custom type. Note: type must be represented by single instance within schema (see also [Type Registry](#))
args | `array` | Array of possible type arguments. Each entry is expected to be an array with keys: **name**, **type**, **description**, **defaultValue**
resolve | `callback` | **function($value, $args, $context, GraphQL\Type\Definition\ResolveInfo $info)** Given the `$value` of this type it is expected to return value for current field. See section on [Data Fetching](#) for details
description | `string` | Plain-text description of this field description for clients (e.g. used by [GraphiQL](https://github.com/graphql/graphiql) for auto-generated documentation)
deprecationReason | `string` | Text describing why this field is deprecated. When not empty - field will not be returned by introspection queries (unless forced)

 
# Shorthand field definitions
Fields can be also defined in **shorthand** notation (without `description`, `args` and `defaultValue`):
```php
'fields' => [
    'id' => Type::id(),
    'fieldName' => $fieldType
]
```
which is equivalent of:
```php
'fields' => [
    'id' => ['type' => Type::id()],
    'fieldName' => ['type' => $fieldName]
]
```
which is in turn equivalent of full form:
```php
'fields' => [
    ['name' => 'id', 'type' => Type::id()],
    ['name' => 'fieldName', 'type' => $fieldName]
]
```
Same shorthand notation applies to field arguments as well.

# Recurring and circular types
Almost all real-world applications contain recurring or circular types. 
Think user friends or nested comments for example. 

**graphql-php** allows such types, but you have to use `callback` in 
option **fields** (and/or **interfaces**).
 
For example:
```php
$userType = null;

$userType = new ObjectType([
    'name' => 'User',
    'fields' => function() use (&$userType) {
        return [
            'email' => [
                'type' => Type::string()
            ],
            'friends' => [
                'type' => Type::listOf($userType)
            ]
        ];
    }
]);
```

Same example for [inheritance style of type definitions](#) using [TypeRegistry](#):
```php
<?php
namespace MyApp;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;

class UserType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'fields' => function() {
                return [
                    'email' => MyTypes::string(),
                    'friends' => MyTypes::listOf(MyTypes::user())
                ];
            }
        ];
        parent::__construct($config);
    }
}

class MyTypes 
{
    private static $user;
    
    public static function user()
    {
        return self::$user ?: (self::$user = new UserType());
    }
    
    public static function string()
    {
        return Type::string();
    }
    
    public static function listOf($type)
    {
        return Type::listOf($type);
    }
}
```

# Field Resolution
Field resolution is the primary mechanism in **graphql-php** for returning actual data for your fields.
It is implemented using `resolveField` callback in type definition or `resolve`
callback in field definition (which has precedence).

Read section on [Data Fetching]() for complete description of this process.