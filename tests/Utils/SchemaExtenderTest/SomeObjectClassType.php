<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils\SchemaExtenderTest;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Custom class-based interface type for testing.
 */
final class SomeObjectClassType extends ObjectType
{
    public function isTypeOf($objectValue, $context, ResolveInfo $info)
    {
        return true;
    }
}
