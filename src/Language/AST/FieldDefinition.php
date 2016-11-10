<?php

namespace GraphQL\Language\AST;

class FieldDefinition extends Node
{
    /**
     * @var string
     */
    public $kind = Node::FIELD_DEFINITION;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var InputValueDefinition[]
     */
    public $arguments;

    /**
     * @var Type
     */
    public $type;

    /**
     * @var Directive[]
     */
    public $directives;
}
