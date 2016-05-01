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
     * @return callable|null
     */
    public function getResolveTypeFn();
}
