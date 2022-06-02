<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

/**
 * export type TypeExtensionNode =
 * | ScalarTypeExtensionNode
 * | ObjectTypeExtensionNode
 * | InterfaceTypeExtensionNode
 * | UnionTypeExtensionNode
 * | EnumTypeExtensionNode
 * | InputObjectTypeExtensionNode;.
 *
 * @property NameNode $name
 */
interface TypeExtensionNode extends TypeSystemExtensionNode
{
}
