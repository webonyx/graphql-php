<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\TestClasses;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

final class MyCustomType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'fields' => [
                'a' => Type::string(),
            ],
        ];
        parent::__construct($config);
    }
}
