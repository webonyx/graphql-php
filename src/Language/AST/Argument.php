<?php
namespace GraphQL\Language\AST;

class Argument extends Node
{
    public $kind = Node::ARGUMENT;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var Value
     */
    public $value;
}
