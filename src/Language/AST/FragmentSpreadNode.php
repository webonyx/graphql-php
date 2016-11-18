<?php
namespace GraphQL\Language\AST;

class FragmentSpreadNode extends Node implements SelectionNode
{
    public $kind = NodeType::FRAGMENT_SPREAD;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var array<DirectiveNode>
     */
    public $directives;
}
