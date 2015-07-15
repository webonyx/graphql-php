<?php
namespace GraphQL\Language\AST;

class ObjectValue extends Node implements Value
{
    public $kind = Node::OBJECT;

    /**
     * @var array<ObjectField>
     */
    public $fields;
}
