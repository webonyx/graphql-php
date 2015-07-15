<?php
namespace GraphQL\Language\AST;

class Variable extends Node
{
    public $kind = Node::VARIABLE;

    /**
     * @var Name
     */
    public $name;
}
