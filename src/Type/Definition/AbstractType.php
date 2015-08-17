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

    /**
     * @return ObjectType
     */
    public function getObjectType($value, ResolveInfo $info);

    /**
     * @param Type $type
     * @return bool
     */
    public function isPossibleType(Type $type);
}
