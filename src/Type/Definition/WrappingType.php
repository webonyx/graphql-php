<?php
namespace GraphQL\Type\Definition;

/*
NonNullType
ListOfType
 */
interface WrappingType
{
    /**
     * @param bool $recurse
     * @return Type
     */
    public function getWrappedType($recurse = false);
}
