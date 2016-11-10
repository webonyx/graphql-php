<?php

namespace GraphQL\Language\AST;

class BooleanValue extends Node implements Value
{
    protected $kind = NodeType::BOOLEAN;

    /**
     * @var string
     */
    public $value;
}
