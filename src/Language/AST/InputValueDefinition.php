<?php
namespace GraphQL\Language\AST;

class InputValueDefinition extends Node
{
    /**
     * @var string
     */
    public $kind = NodeType::INPUT_VALUE_DEFINITION;

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
