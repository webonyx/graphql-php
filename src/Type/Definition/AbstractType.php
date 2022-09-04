<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Deferred;

/**
 * @phpstan-type ResolveTypeReturn ObjectType|string|callable(): (ObjectType|string|null)|Deferred|null
 * @phpstan-type ResolveType callable(mixed $objectValue, mixed $context, ResolveInfo $resolveInfo): ResolveTypeReturn
 */
interface AbstractType
{
    /**
     * Resolves the concrete ObjectType for the given value.
     *
     * @param mixed $objectValue The resolved value for the object type
     * @param mixed $context The context that was passed to GraphQL::execute()
     *
     * @return ObjectType|string|callable|Deferred|null
     *
     * @phpstan-return ResolveTypeReturn
     */
    public function resolveType($objectValue, $context, ResolveInfo $info);
}
