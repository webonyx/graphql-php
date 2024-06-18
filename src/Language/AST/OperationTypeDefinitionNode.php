<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

/**
 * @phpstan-import-type OperationType from OperationDefinitionNode
 */
class OperationTypeDefinitionNode extends Node
{
    public string $kind = NodeKind::OPERATION_TYPE_DEFINITION;

    /** @var OperationType */
    public string $operation;

    public NamedTypeNode $type;
}
