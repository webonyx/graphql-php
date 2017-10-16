<?php
namespace GraphQL\Language\AST;

class IntValueNode extends Node implements ValueNode
{
    public $kind = NodeKind::INT;

    /**
     * @var string
     */
    public $value;
}
