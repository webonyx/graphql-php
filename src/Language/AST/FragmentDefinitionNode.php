<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class FragmentDefinitionNode extends Node implements ExecutableDefinitionNode, HasSelectionSet
{
    public string $kind = NodeKind::FRAGMENT_DEFINITION;

    /** @var NameNode */
    public $name;

    /**
     * Note: fragment variable definitions are experimental and may be changed
     * or removed in the future.
     *
     * Thus, this property is the single exception where this is not always a NodeList but may be null.
     *
     * @var NodeList<VariableDefinitionNode>|null
     */
    public $variableDefinitions;

    /** @var NamedTypeNode */
    public $typeCondition;

    /** @var NodeList<DirectiveNode> */
    public $directives;

    /** @var SelectionSetNode */
    public $selectionSet;
}
