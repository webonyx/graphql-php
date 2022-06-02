<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class OperationTypeDefinitionNode extends Node
{
    public string $kind = NodeKind::OPERATION_TYPE_DEFINITION;

    /**
     * One of 'query' | 'mutation' | 'subscription'.
     *
     * @var string
     */
    public $operation;

    /** @var NamedTypeNode */
    public $type;
}
