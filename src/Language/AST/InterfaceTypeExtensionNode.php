<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class InterfaceTypeExtensionNode extends Node implements TypeExtensionNode
{
    public string $kind = NodeKind::INTERFACE_TYPE_EXTENSION;

    /** @var NameNode */
    public $name;

    /** @var NodeList<DirectiveNode> */
    public $directives;

    /** @var NodeList<NamedTypeNode> */
    public $interfaces;

    /** @var NodeList<FieldDefinitionNode> */
    public $fields;
}
