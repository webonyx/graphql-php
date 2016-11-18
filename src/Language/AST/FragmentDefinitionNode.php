<?php
namespace GraphQL\Language\AST;

class FragmentDefinitionNode extends Node implements DefinitionNode, HasSelectionSet
{
    public $kind = NodeType::FRAGMENT_DEFINITION;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var NamedTypeNode
     */
    public $typeCondition;

    /**
     * @var array<DirectiveNode>
     */
    public $directives;

    /**
     * @var SelectionSetNode
     */
    public $selectionSet;
}
