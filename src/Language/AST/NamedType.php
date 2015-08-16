<?php
namespace GraphQL\Language\AST;

class NamedType extends Node
{
    public $kind = Node::NAMED_TYPE;

    /**
     * @var Name
     */
    public $name;
}
