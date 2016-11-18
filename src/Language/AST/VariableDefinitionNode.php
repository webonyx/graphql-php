<?php
namespace GraphQL\Language\AST;

class VariableDefinitionNode extends Node implements DefinitionNode
{
    public $kind = NodeType::VARIABLE_DEFINITION;

    /**
     * @var VariableNode
     */
    public $variable;

    /**
     * @var TypeNode
     */
    public $type;

    /**
     * @var ValueNode|null
     */
    public $defaultValue;
}
