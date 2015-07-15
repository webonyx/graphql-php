<?php
namespace GraphQL\Language\AST;

class Field extends Node
{
    public $kind = Node::FIELD;

    /**
     * @var Name|null
     */
    public $alias;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var array<Argument>|null
     */
    public $arguments;

    /**
     * @var array<Directive>|null
     */
    public $directives;

    /**
     * @var SelectionSet|null
     */
    public $selectionSet;
}
