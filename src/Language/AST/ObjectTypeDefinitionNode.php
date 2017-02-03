<?php
namespace GraphQL\Language\AST;

class ObjectTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    /**
     * @var string
     */
    public $kind = NodeKind::OBJECT_TYPE_DEFINITION;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var NamedTypeNode[]
     */
    public $interfaces = [];

    /**
     * @var DirectiveNode[]
     */
    public $directives;

    /**
     * @var FieldDefinitionNode[]
     */
    public $fields;

    /**
     * @var string
     */
    public $description;
}
