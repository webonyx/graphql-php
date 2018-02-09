<?php
namespace GraphQL\Language\AST;

class ObjectValueNode extends Node implements ValueNode
{
    public $kind = NodeKind::OBJECT;

    /**
     * @var ObjectFieldNode[]|NodeList
     */
    public $fields;
}
