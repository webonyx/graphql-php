<?php
namespace GraphQL\Language\AST;

class ObjectValueNode extends Node implements ValueNode
{
    public $kind = NodeType::OBJECT;

    /**
     * @var ObjectFieldNode[]
     */
    public $fields;
}
