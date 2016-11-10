<?php
namespace GraphQL\Language\AST;

class Variable extends Node
{
    public $kind = NodeType::VARIABLE;

    /**
     * @var Name
     */
    public $name;
}
