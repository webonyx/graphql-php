<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

/**
export type GraphQLImplementingType =
GraphQLObjectType |
GraphQLInterfaceType;
 */
interface ImplementingType
{
    public function implementsInterface(InterfaceType $interfaceType) : bool;

    /**
     * @return array<int, InterfaceType>
     */
    public function getInterfaces() : array;
}
