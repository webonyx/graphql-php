<?php
namespace GraphQL\Language\AST;

class SelectionSet extends Node
{
    public $kind = Node::SELECTION_SET;

    /**
     * @var array<Selection>
     */
    public $selections;
}
