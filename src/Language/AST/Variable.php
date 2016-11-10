<?php

namespace GraphQL\Language\AST;

class Variable extends Node
{
    protected $kind = NodeType::VARIABLE;

    /**
     * @var Name
     */
    public $name;
}
