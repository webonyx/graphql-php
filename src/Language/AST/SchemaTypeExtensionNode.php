<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class SchemaTypeExtensionNode extends Node implements TypeExtensionNode
{
    /** @var string */
    public $kind = NodeKind::SCHEMA_EXTENSION;

    /** @var NodeList<DirectiveNode> */
    public $directives;

    /** @var NodeList<OperationTypeDefinitionNode> */
    public $operationTypes;
}
