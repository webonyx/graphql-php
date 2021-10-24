<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class ObjectTypeExtensionNode extends Node implements TypeExtensionNode
{
    public string $kind = NodeKind::OBJECT_TYPE_EXTENSION;

    /** @var NameNode */
    public $name;

    /** @var NodeList<NamedTypeNode> */
    public $interfaces;

    /** @var NodeList<DirectiveNode> */
    public $directives;

    /** @var NodeList<FieldDefinitionNode> */
    public $fields;
}
