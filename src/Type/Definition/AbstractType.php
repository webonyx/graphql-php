<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Deferred;

/**
 * @phpstan-type ResolveTypeReturn ObjectType|string|callable(): (ObjectType|string|null)|Deferred|null
 * @phpstan-type ResolveType callable(mixed $objectValue, mixed $context, ResolveInfo $resolveInfo): ResolveTypeReturn
 * @phpstan-type ResolveValue callable(mixed $objectValue, mixed $context, ResolveInfo $resolveInfo): mixed
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

    /**
     * Resolves/transforms the value after type resolution.
     *
     * @param mixed $objectValue The resolved value for the object type
     * @param mixed $context The context that was passed to GraphQL::execute()
     *
     * @return mixed The transformed value (or original if no transformation needed)
     */
    public function resolveValue($objectValue, $context, ResolveInfo $info);
}
