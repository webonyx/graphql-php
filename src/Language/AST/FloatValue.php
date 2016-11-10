<?php

namespace GraphQL\Language\AST;

class FloatValue extends Node implements Value
{
    protected $kind = Node::FLOAT;

    /**
     * @var string
     */
    public $value;
}
