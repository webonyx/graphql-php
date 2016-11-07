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
     * Resolves concrete ObjectType for given object value
     *
     * @param $objectValue
     * @param $context
     * @param ResolveInfo $info
     * @return mixed
     */
    public function resolveType($objectValue, $context, ResolveInfo $info);
}
