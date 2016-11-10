<?php

namespace GraphQL\Language\AST;

class EnumValue extends Node implements Value
{
    protected $kind = Node::ENUM;

    /**
     * @var string
     */
    public $value;
}
