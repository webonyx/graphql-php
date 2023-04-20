<?php declare(strict_types=1);

namespace GraphQL\Tests\Type\TestClasses;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Note: named OtherCustom vs OtherCustomType intentionally.
 */
final class OtherCustom extends ObjectType
{
    public function __construct()
    {
        $config = [
            'fields' => [
                'b' => Type::string(),
            ],
        ];
        parent::__construct($config);
    }
}
