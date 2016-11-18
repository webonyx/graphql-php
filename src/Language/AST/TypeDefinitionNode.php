<?php
namespace GraphQL\Language\AST;

interface TypeDefinitionNode extends TypeSystemDefinitionNode
{
    /**
    export type TypeDefinitionNode = ScalarTypeDefinitionNode
    | ObjectTypeDefinitionNode
    | InterfaceTypeDefinitionNode
    | UnionTypeDefinitionNode
    | EnumTypeDefinitionNode
    | InputObjectTypeDefinitionNode
     */
}
