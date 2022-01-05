<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

class SchemaTypeExtensionNode extends Node implements TypeExtensionNode
{
    public string $kind = NodeKind::SCHEMA_EXTENSION;

    /** @var NodeList<DirectiveNode> */
    public NodeList $directives;

    /** @var NodeList<OperationTypeDefinitionNode> */
    public NodeList $operationTypes;
}
