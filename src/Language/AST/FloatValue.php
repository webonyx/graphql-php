<?php
namespace GraphQL\Language\AST;

class FloatValue extends Node implements Value
{
    public $kind = NodeType::FLOAT;

    /**
     * @var string
     */
    public $value;
}
