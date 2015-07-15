<?php
namespace GraphQL\Language\AST;

class Directive extends Node
{
    public $kind = Node::DIRECTIVE;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var Value
     */
    public $value;
}
