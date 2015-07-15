<?php
namespace GraphQL\Language\AST;


class StringValue extends Node implements Value
{
    public $kind = Node::STRING;

    /**
     * @var string
     */
    public $value;
}