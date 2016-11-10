<?php

namespace GraphQL\Language\AST;

class StringValue extends Node implements Value
{
    protected $kind = Node::STRING;

    /**
     * @var string
     */
    public $value;
}
