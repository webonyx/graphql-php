<?php
namespace GraphQL\Language\AST;

class SelectionSetNode extends Node
{
    public $kind = NodeKind::SELECTION_SET;

    /**
     * @var SelectionNode[]
     */
    public $selections;
}
