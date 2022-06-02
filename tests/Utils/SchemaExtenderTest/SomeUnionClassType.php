<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils\SchemaExtenderTest;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\UnionType;

/**
 * Custom class-based union type for testing.
 */
final class SomeUnionClassType extends UnionType
{
    public ObjectType $concrete;

    public function resolveType($objectValue, $context, ResolveInfo $info)
    {
        return $this->concrete;
    }
}
