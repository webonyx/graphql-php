<?php
namespace GraphQL\Language\AST;


class BooleanValueNode extends Node implements ValueNode
{
    public $kind = NodeKind::BOOLEAN;

    /**
     * @var string
     */
    public $value;
}
