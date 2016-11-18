<?php
namespace GraphQL\Language\AST;

class NullValueNode extends Node implements ValueNode
{
    public $kind = NodeType::NULL;
}
