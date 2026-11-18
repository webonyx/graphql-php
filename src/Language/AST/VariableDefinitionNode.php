<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

/**
 * @phpstan-import-type ConstValueNodeVariants from ConstValueNode
 */
class VariableDefinitionNode extends Node implements DefinitionNode
{
    public string $kind = NodeKind::VARIABLE_DEFINITION;

    public VariableNode $variable;

    /** @var NamedTypeNode|ListTypeNode|NonNullTypeNode */
    public TypeNode $type;

    /** @var ConstValueNodeVariants|null */
    public ?ConstValueNode $defaultValue = null;

    /** @var NodeList<DirectiveNode> */
    public NodeList $directives;

    public function __construct(array $vars)
    {
        parent::__construct($vars);
        $this->directives ??= new NodeList([]);
    }
}
