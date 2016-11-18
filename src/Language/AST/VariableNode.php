<?php
namespace GraphQL\Language\AST;

class VariableNode extends Node
{
    public $kind = NodeType::VARIABLE;

    /**
     * @var NameNode
     */
    public $name;
}
