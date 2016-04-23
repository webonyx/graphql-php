<?php
namespace GraphQL\Language\AST;

class InputObjectTypeDefinition extends Node implements TypeDefinition
{
    /**
     * @var string
     */
    public $kind = Node::INPUT_OBJECT_TYPE_DEFINITION;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var InputValueDefinition[]
     */
    public $fields;
}
