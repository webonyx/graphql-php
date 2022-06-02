<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

class SchemaDefinitionNode extends Node implements TypeSystemDefinitionNode
{
    public string $kind = NodeKind::SCHEMA_DEFINITION;

    /** @var NodeList<DirectiveNode> */
    public NodeList $directives;

    /** @var NodeList<OperationTypeDefinitionNode> */
    public NodeList $operationTypes;
}
