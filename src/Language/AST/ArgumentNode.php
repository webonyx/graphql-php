<?php
namespace GraphQL\Language\AST;

class ArgumentNode extends Node
{
    public $kind = NodeType::ARGUMENT;

    /**
     * @var ValueNode
     */
    public $value;

    /**
     * @var NameNode
     */
    public $name;
}
