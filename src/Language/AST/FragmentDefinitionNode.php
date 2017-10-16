<?php
namespace GraphQL\Language\AST;

class FragmentDefinitionNode extends Node implements DefinitionNode, HasSelectionSet
{
    public $kind = NodeKind::FRAGMENT_DEFINITION;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var NamedTypeNode
     */
    public $typeCondition;

    /**
     * @var DirectiveNode[]
     */
    public $directives;

    /**
     * @var SelectionSetNode
     */
    public $selectionSet;
}
