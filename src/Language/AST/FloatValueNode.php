<?php
namespace GraphQL\Language\AST;

class FloatValueNode extends Node implements ValueNode
{
    public $kind = NodeType::FLOAT;

    /**
     * @var string
     */
    public $value;
}
