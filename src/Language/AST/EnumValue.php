<?php
namespace GraphQL\Language\AST;

class EnumValue extends Node implements Value
{
    public $kind = NodeType::ENUM;

    /**
     * @var string
     */
    public $value;
}
