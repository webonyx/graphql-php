<?php
namespace GraphQL\Language\AST;

class EnumValueNode extends Node implements ValueNode
{
    public $kind = NodeKind::ENUM;

    /**
     * @var string
     */
    public $value;
}
