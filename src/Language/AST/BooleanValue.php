<?php

namespace GraphQL\Language\AST;

class BooleanValue extends Node implements Value
{
    protected $kind = Node::BOOLEAN;

    /**
     * @var string
     */
    public $value;
}
