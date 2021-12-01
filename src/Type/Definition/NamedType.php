<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;

/**
 * export type NamedType =
 * | ScalarType
 * | ObjectType
 * | InterfaceType
 * | UnionType
 * | EnumType
 * | InputObjectType;
 *
 * @property (Node&TypeDefinitionNode)|null $astNode
 * @property array<int, Node&TypeExtensionNode> $extensionASTNodes
 */
interface NamedType
{
}
