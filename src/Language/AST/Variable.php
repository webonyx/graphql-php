<?php

namespace GraphQL\Language\AST;

class Variable extends Node
{
    protected $kind = Node::VARIABLE;

    /**
     * @var Name
     */
    public $name;
}
