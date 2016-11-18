<?php
namespace GraphQL\Language\AST;

class EnumValueNode extends Node implements ValueNode
{
    public $kind = NodeType::ENUM;

    /**
     * @var string
     */
    public $value;
}
