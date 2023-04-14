<?php declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Error\Error;
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
 * | InputObjectType;.
 *
 * @property string $name
 * @property string|null $description
 * @property (Node&TypeDefinitionNode)|null $astNode
 * @property array<int, Node&TypeExtensionNode> $extensionASTNodes
 */
interface NamedType
{
    /** @throws Error */
    public function assertValid(): void;

    /** Is this type a built-in type? */
    public function isBuiltInType(): bool;

    public function name(): string;

    public function description(): ?string;

    /** @return (Node&TypeDefinitionNode)|null */
    public function astNode(): ?Node;

    /** @return array<int, Node&TypeExtensionNode> */
    public function extensionASTNodes(): array;
}
