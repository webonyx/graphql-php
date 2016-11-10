<?php
namespace GraphQL\Language\AST;

class Directive extends Node
{
    public $kind = NodeType::DIRECTIVE;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var Argument[]
     */
    public $arguments;
}
