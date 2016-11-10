<?php

namespace GraphQL\Language\AST;

class ListValue extends Node implements Value
{
    protected $kind = Node::LST;

    /**
     * @var array<Value>
     */
    public $values;
}
