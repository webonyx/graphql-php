<?php

namespace GraphQL\Language\AST;


class ObjectField extends Node
{
    protected $kind = Node::OBJECT_FIELD;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var Value
     */
    public $value;
}
