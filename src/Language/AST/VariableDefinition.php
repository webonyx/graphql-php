<?php
namespace GraphQL\Language\AST;

class VariableDefinition extends Node implements Definition
{
    public $kind = NodeType::VARIABLE_DEFINITION;

    /**
     * @var Variable
     */
    public $variable;

    /**
     * @var Type
     */
    public $type;

    /**
     * @var Value|null
     */
    public $defaultValue;
}
