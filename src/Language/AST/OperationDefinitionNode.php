<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class OperationDefinitionNode extends Node implements ExecutableDefinitionNode, HasSelectionSet
{
    /** @var string */
    public $kind = NodeKind::OPERATION_DEFINITION;

    /** @var NameNode|null */
    public $name;

    /** @var string (oneOf 'query', 'mutation')) */
    public $operation;

    /** @var NodeList<VariableDefinitionNode> */
    public $variableDefinitions;

    /** @var DirectiveNode[] */
    public $directives;

    /** @var SelectionSetNode */
    public $selectionSet;

    /**
     * @param mixed[] $edits
     */
    public function setEdits(array $edits)
    {
        $this->selectionSet        = $edits['selectionSet'] ?? $this->selectionSet;
        $this->directives          = $edits['directives'] ?? $this->directives;
        $this->variableDefinitions = $edits['variableDefinitions'] ?? $this->variableDefinitions;
    }
}
