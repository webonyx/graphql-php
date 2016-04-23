<?php
namespace GraphQL\Language\AST;


class ScalarTypeDefinition extends Node implements TypeDefinition
{
    public $kind = Node::SCALAR_TYPE_DEFINITION;

    /**
     * @var Name
     */
    public $name;
}
