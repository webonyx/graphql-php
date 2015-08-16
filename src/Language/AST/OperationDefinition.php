<?php
namespace GraphQL\Language\AST;

class OperationDefinition extends NamedType implements Definition
{
    /**
     * @var string
     */
    public $kind = Node::OPERATION_DEFINITION;

    /**
     * @var string (oneOf 'query', 'mutation'))
     */
    public $operation;

    /**
     * @var array<VariableDefinition>
     */
    public $variableDefinitions;

    /**
     * @var array<Directive>
     */
    public $directives;

    /**
     * @var SelectionSet
     */
    public $selectionSet;
}
