<?php declare(strict_types=1);

namespace GraphQL\Utils;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;

/**
 * A way to track interface implementations.
 *
 * Distinguishes between implementations by ObjectTypes and InterfaceTypes.
 */
class InterfaceImplementations
{
    /** @var array<int, ObjectType> */
    private $objects;

    /** @var array<int, InterfaceType> */
    private $interfaces;

    /**
     * @param array<int, ObjectType>    $objects
     * @param array<int, InterfaceType> $interfaces
     */
    public function __construct(array $objects, array $interfaces)
    {
        $this->objects = $objects;
        $this->interfaces = $interfaces;
    }

    /** @return array<int, ObjectType> */
    public function objects(): array
    {
        return $this->objects;
    }

    /** @return array<int, InterfaceType> */
    public function interfaces(): array
    {
        return $this->interfaces;
    }
}
