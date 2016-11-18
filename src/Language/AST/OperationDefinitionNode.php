<?php
namespace GraphQL\Language\AST;

class OperationDefinitionNode extends Node implements DefinitionNode, HasSelectionSet
{
    /**
     * @var string
     */
    public $kind = NodeType::OPERATION_DEFINITION;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var string (oneOf 'query', 'mutation'))
     */
    public $operation;

    /**
     * @var array<VariableDefinitionNode>
     */
    public $variableDefinitions;

    /**
     * @var array<DirectiveNode>
     */
    public $directives;

    /**
     * @var SelectionSetNode
     */
    public $selectionSet;
}
