<?php

namespace GraphQL\Language\AST;

class EnumValueDefinition extends Node
{
    /**
     * @var string
     */
    public $kind = Node::ENUM_VALUE_DEFINITION;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var Directive[]
     */
    public $directives;
}
