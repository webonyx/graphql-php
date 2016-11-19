<?php
namespace GraphQL\Language\AST;

class VariableNode extends Node
{
    public $kind = NodeKind::VARIABLE;

    /**
     * @var NameNode
     */
    public $name;
}
