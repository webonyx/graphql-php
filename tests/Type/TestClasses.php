<?php
namespace GraphQL\Tests\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class MyCustomType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'fields' => [
                'a' => Type::string()
            ]
        ];
        parent::__construct($config);
    }
}

class OtherCustom extends ObjectType
{
    public function __construct()
    {
        $config = [
            'fields' => [
                'b' => Type::string()
            ]
        ];
        parent::__construct($config);
    }
}
