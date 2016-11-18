<?php
namespace GraphQL\Language\AST;


class ObjectFieldNode extends Node
{
    public $kind = NodeType::OBJECT_FIELD;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var ValueNode
     */
    public $value;
}
