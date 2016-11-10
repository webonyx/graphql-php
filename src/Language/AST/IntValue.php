<?php

namespace GraphQL\Language\AST;

class IntValue extends Node implements Value
{
    protected $kind = NodeType::INT;

    /**
     * @var string
     */
    public $value;
}
