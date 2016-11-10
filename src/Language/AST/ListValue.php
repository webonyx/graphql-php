<?php

namespace GraphQL\Language\AST;

class ListValue extends Node implements Value
{
    public $kind = NodeType::LST;

    /**
     * @var array<Value>
     */
    public $values;
}
