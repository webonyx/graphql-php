<?php
namespace GraphQL\Type\Definition;


interface WrappingType
{
/*
NonNullType
ListOfType
 */
    public function getWrappedType($recurse = false);
}
