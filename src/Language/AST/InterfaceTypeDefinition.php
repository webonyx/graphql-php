<?php

namespace GraphQL\Language\AST;

class InterfaceTypeDefinition extends Node implements TypeDefinition
{
    /**
     * @var string
     */
    protected $kind = NodeType::INTERFACE_TYPE_DEFINITION;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var Directive[]
     */
    public $directives;

    /**
     * @var FieldDefinition[]
     */
    public $fields = [];
}
