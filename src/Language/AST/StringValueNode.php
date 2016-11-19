<?php
namespace GraphQL\Language\AST;

class StringValueNode extends Node implements ValueNode
{
    public $kind = NodeKind::STRING;

    /**
     * @var string
     */
    public $value;
}
