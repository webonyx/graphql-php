<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

class OperationTypeDefinitionNode extends Node
{
    public string $kind = NodeKind::OPERATION_TYPE_DEFINITION;

    /**
     * @var 'query'|'mutation'|'subscription'
     */
    public string $operation;

    public NamedTypeNode $type;
}
