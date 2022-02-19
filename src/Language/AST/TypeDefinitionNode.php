<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

/**
 * export type TypeDefinitionNode = ScalarTypeDefinitionNode
 * | ObjectTypeDefinitionNode
 * | InterfaceTypeDefinitionNode
 * | UnionTypeDefinitionNode
 * | EnumTypeDefinitionNode
 * | InputObjectTypeDefinitionNode.
 *
 * @property NameNode $name
 */
interface TypeDefinitionNode extends TypeSystemDefinitionNode
{
}
