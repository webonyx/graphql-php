<?php
namespace GraphQL\Language\AST;

class ScalarTypeDefinition extends Node implements TypeDefinition
{
    /**
     * @var string
     */
    public $kind = NodeType::SCALAR_TYPE_DEFINITION;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var Directive[]
     */
    public $directives;
}
