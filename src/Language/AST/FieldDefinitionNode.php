<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class FieldDefinitionNode extends Node
{
    /** @var string */
    public $kind = NodeKind::FIELD_DEFINITION;

    /** @var NameNode */
    public $name;

    /** @var NodeList<InputValueDefinitionNode> */
    public $arguments;

    /** @var NamedTypeNode|ListTypeNode|NonNullTypeNode */
    public $type;

    /** @var NodeList<DirectiveNode> */
    public $directives;

    /** @var StringValueNode|null */
    public $description;
}
