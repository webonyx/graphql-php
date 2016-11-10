<?php
namespace GraphQL\Language\AST;


class BooleanValue extends Node implements Value
{
    public $kind = NodeType::BOOLEAN;

    /**
     * @var string
     */
    public $value;
}
