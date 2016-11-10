<?php

namespace GraphQL\Language\AST;

class ObjectValue extends Node implements Value
{
    protected $kind = NodeType::OBJECT;

    /**
     * @var array<ObjectField>
     */
    public $fields;
}
