<?php
namespace GraphQL\Language\AST;

class ArgumentNode extends Node
{
    public $kind = NodeKind::ARGUMENT;

    /**
     * @var ValueNode
     */
    public $value;

    /**
     * @var NameNode
     */
    public $name;
}
