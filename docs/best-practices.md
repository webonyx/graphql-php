# Type Registry

Every type in the schema must be represented by a single class instance.
**graphql-php** throws when it discovers several instances with the same **name** in the schema.

The typical way to do this is to create a registry of your types:

```php
<?php
namespace MyApp;

class TypeRegistry
{
    private $myAType;
    private $myBType;
    
    public function myAType()
    {
        return $this->myAType ?: ($this->myAType = new MyAType($this));
    }
    
    public function myBType()
    {
        return $this->myBType ?: ($this->myBType = new MyBType($this));
    }
}
```

Use this registry in type definitions:

```php
<?php
namespace MyApp;
use GraphQL\Type\Definition\ObjectType;

class MyAType extends ObjectType
{
    public function __construct(TypeRegistry $types) 
    {
        parent::__construct([
            'fields' => [
                'b' => $types->myBType()                
            ]
        ]);
    }
}
```
Obviously, you can automate this registry as you wish to reduce boilerplate or even
introduce Dependency Injection Container if your types have other dependencies.

Alternatively, all methods of the registry could be static - then there is no need
to pass it in the constructor - instead use **TypeRegistry::myAType()** in your type definitions.
