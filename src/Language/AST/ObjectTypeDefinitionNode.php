<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class ObjectTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    /** @var string */
    public $kind = NodeKind::OBJECT_TYPE_DEFINITION;

    /** @var NameNode */
    public $name;

    /** @var NodeList<NamedTypeNode> */
    public $interfaces;

    /** @var NodeList<DirectiveNode> */
    public $directives;

    /** @var NodeList<FieldDefinitionNode> */
    public $fields;

    /** @var StringValueNode|null */
    public $description;
}
