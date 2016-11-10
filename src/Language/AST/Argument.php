<?php

namespace GraphQL\Language\AST;

class Argument extends Node
{
    protected $kind = NodeType::ARGUMENT;

    /**
     * @var Value
     */
    public $value;

    /**
     * @var Name
     */
    public $name;
}
