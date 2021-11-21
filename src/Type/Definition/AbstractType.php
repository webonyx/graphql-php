<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

/**
 * @phpstan-type AbstractTypeAlias InterfaceType|UnionType
 */
interface AbstractType
{
    /**
     * Resolves the concrete ObjectType for the given value.
     *
     * @param mixed $objectValue The resolved value for the object type
     * @param mixed $context     The context that was passed to GraphQL::execute()
     *
     * @return ObjectType|callable(): ObjectType|null
     */
    public function resolveType($objectValue, $context, ResolveInfo $info);
}
