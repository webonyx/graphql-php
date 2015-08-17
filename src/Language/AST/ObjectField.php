<?php
namespace GraphQL\Language\AST;


class ObjectField extends Node
{
    public $kind = Node::OBJECT_FIELD;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var Value
     */
    public $value;
}
