<?php

declare(strict_types=1);

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
    /** @var ObjectType[] */
    private $objects;

    /** @var InterfaceType[] */
    private $interfaces;

    /**
     * Create a new InterfaceImplementations instance.
     *
     * @param ObjectType[]    $objects
     * @param InterfaceType[] $interfaces
     */
    public function __construct(array $objects, array $interfaces)
    {
        $this->objects    = $objects;
        $this->interfaces = $interfaces;
    }

    /**
     * @return ObjectType[]
     */
    public function objects() : array
    {
        return $this->objects;
    }

    /**
     * @return InterfaceType[]
     */
    public function interfaces() : array
    {
        return $this->interfaces;
    }
}
