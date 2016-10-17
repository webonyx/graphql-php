<?php
namespace GraphQL\Type\Definition;

/*
export type GraphQLAbstractType =
GraphQLInterfaceType |
GraphQLUnionType;
*/
interface AbstractType
{
    /**
     * @return callable|null
     */
    public function getResolveTypeFn();
}
