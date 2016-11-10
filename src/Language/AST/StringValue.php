<?php
namespace GraphQL\Language\AST;

class StringValue extends Node implements Value
{
    public $kind = NodeType::STRING;

    /**
     * @var string
     */
    public $value;
}
