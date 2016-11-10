<?php

namespace GraphQL\Language\AST;

class FloatValue extends Node implements Value
{
    protected $kind = NodeType::FLOAT;

    /**
     * @var string
     */
    public $value;
}
