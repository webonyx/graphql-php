<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class ObjectTypeExtensionNode extends Node implements TypeExtensionNode
{
    /** @var string */
    public $kind = NodeKind::OBJECT_TYPE_EXTENSION;

    /** @var NameNode */
    public $name;

    /** @var NamedTypeNode[] */
    public $interfaces = [];

    /** @var NodeList<DirectiveNode> */
    public $directives;

    /**
     * TODO: should this be annoted as a NodeList<FieldDefinitionNode>?
     * @var FieldDefinitionNode[]
     */
    public $fields;
}
