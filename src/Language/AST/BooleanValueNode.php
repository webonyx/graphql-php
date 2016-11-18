<?php
namespace GraphQL\Language\AST;


class BooleanValueNode extends Node implements ValueNode
{
    public $kind = NodeType::BOOLEAN;

    /**
     * @var string
     */
    public $value;
}
