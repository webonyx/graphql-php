<?php
namespace GraphQL\Language\AST;

class StringValueNode extends Node implements ValueNode
{
    public $kind = NodeType::STRING;

    /**
     * @var string
     */
    public $value;
}
