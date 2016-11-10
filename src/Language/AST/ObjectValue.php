<?php

namespace GraphQL\Language\AST;

class ObjectValue extends Node implements Value
{
    protected $kind = Node::OBJECT;

    /**
     * @var array<ObjectField>
     */
    public $fields;
}
