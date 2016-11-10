<?php

namespace GraphQL\Language\AST;

class IntValue extends Node implements Value
{
    protected $kind = Node::INT;

    /**
     * @var string
     */
    public $value;
}
