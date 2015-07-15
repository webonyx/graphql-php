<?php
namespace GraphQL\Language\AST;


class IntValue extends Node implements Value
{
    public $kind = Node::INT;

    /**
     * @var string
     */
    public $value;
}
