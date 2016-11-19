<?php
namespace GraphQL\Language\AST;

class FragmentSpreadNode extends Node implements SelectionNode
{
    public $kind = NodeKind::FRAGMENT_SPREAD;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var DirectiveNode[]
     */
    public $directives;
}
