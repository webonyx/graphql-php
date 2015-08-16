<?php
namespace GraphQL\Language\AST;

class Argument extends NamedType
{
    public $kind = Node::ARGUMENT;

    /**
     * @var Value
     */
    public $value;
}
