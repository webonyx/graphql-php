<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class FragmentDefinitionNode extends Node implements ExecutableDefinitionNode, HasSelectionSet
{
    /** @var string */
    public $kind = NodeKind::FRAGMENT_DEFINITION;

    /** @var NameNode */
    public $name;

    /**
     * Note: fragment variable definitions are experimental and may be changed
     * or removed in the future.
     *
     * @var NodeList<VariableDefinitionNode>
     */
    public $variableDefinitions;

    /** @var NamedTypeNode */
    public $typeCondition;

    /** @var NodeList<DirectiveNode> */
    public $directives;

    /** @var SelectionSetNode */
    public $selectionSet;

    /**
     * @param mixed[] $edits
     */
    public function setEdits(array $edits)
    {
        $this->selectionSet = $edits['selectionSet'] ?? $this->selectionSet;
        $this->variableDefinitions = $edits['variableDefinitions'] ?? $this->variableDefinitions;
        $this->directives = $edits['directives'] ?? $this->directives;
    }
}
