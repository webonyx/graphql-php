# Interfaces
In GraphQL an Interface is an abstract type that includes a certain set of fields that a 
type must include to implement the interface.

In **graphql-php** interface type is an instance of `GraphQL\Type\Definition\InterfaceType` 
(or one of it subclasses) which accepts configuration array in constructor:

```php
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

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
        ]
    ],
    'resolveType' => function ($obj) {
        if ($obj->type === 'human') {
            return MyTypes::human();            
        } else {
            return MyTypes::droid();
        }
        return null;
    }
]);
```

# Abstract Type Resolution
