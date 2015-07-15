<?php
namespace GraphQL\Language\AST;

class EnumValue extends Node implements Value
{
    public $kind = Node::ENUM;

    /**
     * @var string
     */
    public $value;
}
