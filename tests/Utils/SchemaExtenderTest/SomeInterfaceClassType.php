<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils\SchemaExtenderTest;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

/** Custom class-based interface type for testing. */
final class SomeInterfaceClassType extends InterfaceType
{
    public ObjectType $concrete;

    public function resolveType($objectValue, $context, ResolveInfo $info): ObjectType
    {
        return $this->concrete;
    }
}
