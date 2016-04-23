<?php
namespace GraphQL\Language\AST;

class InterfaceTypeDefinition extends Node implements TypeDefinition
{
    /**
     * @var string
     */
    public $kind = Node::INTERFACE_TYPE_DEFINITION;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var FieldDefinition[]
     */
    public $fields = [];
}