<?php

namespace GraphQL\Language\AST;

class InputValueDefinition extends Node
{
    /**
     * @var string
     */
    protected $kind = Node::INPUT_VALUE_DEFINITION;

    /**
     * @var Name
     */
    public $name;

    /**
     * @var Type
     */
    public $type;

    /**
     * @var Value
     */
    public $defaultValue;

    /**
     * @var Directive[]
     */
    public $directives;
}
