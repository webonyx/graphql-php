<?php
namespace GraphQL\Language\AST;

class FloatValueNode extends Node implements ValueNode
{
    public $kind = NodeKind::FLOAT;

    /**
     * @var string
     */
    public $value;
}
