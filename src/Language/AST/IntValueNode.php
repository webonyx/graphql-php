<?php
namespace GraphQL\Language\AST;

class IntValueNode extends Node implements ValueNode
{
    public $kind = NodeType::INT;

    /**
     * @var string
     */
    public $value;
}
