<?php
namespace GraphQL\Language\AST;

class ArrayValue extends Node implements Value
{
    public $kind = Node::ARR;

    /**
     * @var array<Value>
     */
    public $values;
}
