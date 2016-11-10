<?php

namespace GraphQL\Language\AST;

class SelectionSet extends Node
{
    protected $kind = Node::SELECTION_SET;

    /**
     * @var array<Selection>
     */
    public $selections;
}
