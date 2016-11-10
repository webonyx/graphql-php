<?php
namespace GraphQL\Language\AST;

class IntValue extends Node implements Value
{
    public $kind = NodeType::INT;

    /**
     * @var string
     */
    public $value;
}
