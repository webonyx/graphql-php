<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\TestClasses;

use GraphQL\Type\Definition\ObjectType;

final class CustomWithObject extends ObjectType
{
    public function __construct()
    {
        parent::__construct([
            'fields' => [
                'customA' => new MyCustomType(),
                'customB' => new OtherCustom(),
            ],
        ]);
    }
}
