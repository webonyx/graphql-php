<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class SchemaTypeExtensionNode extends Node implements TypeExtensionNode
{
    /** @var string */
    public $kind = NodeKind::SCHEMA_EXTENSION;

    /** @var NodeList<DirectiveNode>|null */
    public $directives;

    /** @var NodeList<OperationTypeDefinitionNode>|null */
    public $operationTypes;
}
