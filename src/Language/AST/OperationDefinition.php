<?php

namespace GraphQL\Language\AST;

class OperationDefinition extends Node implements Definition, HasSelectionSet
{
    /**
     * @var string
     */
    protected $kind = NodeType::OPERATION_DEFINITION;

    /**
     * @var Name
     */
    protected $name;

    /**
     * @var string (oneOf 'query', 'mutation'))
     */
    protected $operation;

    /**
     * @var array<VariableDefinition>
     */
    protected $variableDefinitions;

    /**
     * @var array<Directive>
     */
    protected $directives;

    /**
     * @var SelectionSet
     */
    protected $selectionSet;

    /**
     * OperationDefinition constructor.
     *
     * @param Name         $name
     * @param              $operation
     * @param array        $variableDefinitions
     * @param array        $directives
     * @param SelectionSet $selectionSet
     * @param null         $loc
     */
    public function __construct(
        $name,
        $operation,
        array $variableDefinitions = null,
        array $directives,
        SelectionSet $selectionSet,
        $loc = null
    )
    {
        $this->name = $name;
        $this->operation = $operation;
        $this->variableDefinitions = $variableDefinitions;
        $this->directives = $directives;
        $this->selectionSet = $selectionSet;
        $this->loc = $loc;
    }

    /**
     * @return Name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param Name $name
     *
     * @return OperationDefinition
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getOperation()
    {
        return $this->operation;
    }

    /**
     * @param string $operation
     *
     * @return OperationDefinition
     */
    public function setOperation($operation)
    {
        $this->operation = $operation;

        return $this;
    }

    /**
     * @return array
     */
    public function getVariableDefinitions()
    {
        return $this->variableDefinitions;
    }

    /**
     * @param array $variableDefinitions
     *
     * @return OperationDefinition
     */
    public function setVariableDefinitions($variableDefinitions)
    {
        $this->variableDefinitions = $variableDefinitions;

        return $this;
    }

    /**
     * @return array
     */
    public function getDirectives()
    {
        return $this->directives;
    }

    /**
     * @param array $directives
     *
     * @return OperationDefinition
     */
    public function setDirectives($directives)
    {
        $this->directives = $directives;

        return $this;
    }

    /**
     * @return SelectionSet
     */
    public function getSelectionSet()
    {
        return $this->selectionSet;
    }

    /**
     * @param SelectionSet $selectionSet
     *
     * @return OperationDefinition
     */
    public function setSelectionSet($selectionSet)
    {
        $this->selectionSet = $selectionSet;

        return $this;
    }
}
