<?php

namespace GraphQL\Language\AST;

class Argument extends Node
{
    protected $kind = Node::ARGUMENT;

    /**
     * @var Value
     */
    public $value;

    /**
     * @var Name
     */
    public $name;
}
