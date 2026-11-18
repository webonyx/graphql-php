<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

/**
 * @phpstan-import-type ValueNodeVariants from ValueNode
 */
class InputValueDefinitionNode extends Node
{
    public string $kind = NodeKind::INPUT_VALUE_DEFINITION;

    public NameNode $name;

    /** @var NamedTypeNode|ListTypeNode|NonNullTypeNode */
    public TypeNode $type;

    /** @var ValueNodeVariants|null */
    public ?ValueNode $defaultValue = null;

    /** @var NodeList<DirectiveNode> */
    public NodeList $directives;

    public ?StringValueNode $description = null;
}
