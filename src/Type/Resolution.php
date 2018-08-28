<?php

declare(strict_types=1);

namespace GraphQL\Type;

use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * EXPERIMENTAL!
 * This interface can be removed or changed in future versions without a prior notice.
 *
 * Interface Resolution
 */
interface Resolution
{
    /**
     * Returns instance of type with given $name for GraphQL Schema
     *
     * @param string $name
     * @return Type
     */
    public function resolveType($name);

    /**
     * Returns instances of possible ObjectTypes for given InterfaceType or UnionType
     *
     * @return ObjectType[]
     */
    public function resolvePossibleTypes(AbstractType $type);
}
