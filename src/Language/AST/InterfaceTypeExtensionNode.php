<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class InterfaceTypeExtensionNode extends Node implements TypeExtensionNode
{
    /** @var string */
    public $kind = NodeKind::INTERFACE_TYPE_EXTENSION;

    /** @var NameNode */
    public $name;

    /** @var NodeList<DirectiveNode>|null */
    public $directives;

    /** @var NodeList<FieldDefinitionNode>|null */
    public $fields;
}
