<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

/**
 * @phpstan-type OperationType 'query'|'mutation'|'subscription'
 */
class OperationDefinitionNode extends Node implements ExecutableDefinitionNode, HasSelectionSet
{
    public string $kind = NodeKind::OPERATION_DEFINITION;

    public ?NameNode $name = null;

    /**
     * @var OperationType
     */
    public string $operation;

    /** @var NodeList<VariableDefinitionNode> */
    public NodeList $variableDefinitions;

    /** @var NodeList<DirectiveNode> */
    public NodeList $directives;

    public SelectionSetNode $selectionSet;

    public function getSelectionSet(): SelectionSetNode
    {
        return $this->selectionSet;
    }
}
