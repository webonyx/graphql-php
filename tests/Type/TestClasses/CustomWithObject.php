<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\TestClasses;

use GraphQL\Type\Definition\ObjectType;

final class CustomWithObject extends ObjectType
{
    public function __construct()
    {
        $config = [
            'fields' => [
                'customA' => new MyCustomType(),
                'customB' => new OtherCustom(),
            ],
        ];
        parent::__construct($config);
    }
}
