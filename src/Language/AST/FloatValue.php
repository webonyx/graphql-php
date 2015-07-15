<?php
namespace GraphQL\Language\AST;


class FloatValue extends Node implements Value
{
    public $kind = Node::FLOAT;

    /**
     * @var string
     */
    public $value;
}