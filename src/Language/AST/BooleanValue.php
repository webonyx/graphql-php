<?php
namespace GraphQL\Language\AST;


class BooleanValue extends Node implements Value
{
    public $kind = Node::BOOLEAN;

    /**
     * @var string
     */
    public $value;
}