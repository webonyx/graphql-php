<?php

namespace GraphQL\Language\AST;

class EnumValue extends Node implements Value
{
    protected $kind = NodeType::ENUM;

    /**
     * @var string
     */
    public $value;
}
