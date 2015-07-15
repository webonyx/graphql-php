<?php
namespace GraphQL\Type\Definition;


interface AbstractType
{
/*
export type GraphQLAbstractType =
GraphQLInterfaceType |
GraphQLUnionType;
*/
    /**
     * @return array<ObjectType>
     */
    public function getPossibleTypes();
}
