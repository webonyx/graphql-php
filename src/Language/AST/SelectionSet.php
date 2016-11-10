<?php
namespace GraphQL\Language\AST;

class SelectionSet extends Node
{
    public $kind = NodeType::SELECTION_SET;

    /**
     * @var array<Selection>
     */
    public $selections;
}
