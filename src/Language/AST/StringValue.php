<?php

namespace GraphQL\Language\AST;

class StringValue extends Node implements Value
{
    protected $kind = NodeType::STRING;

    /**
     * @var string
     */
    public $value;
}
