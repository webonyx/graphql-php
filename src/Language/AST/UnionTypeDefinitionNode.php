<?php
namespace GraphQL\Language\AST;

class UnionTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    /**
     * @var string
     */
    public $kind = NodeKind::UNION_TYPE_DEFINITION;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var DirectiveNode[]
     */
    public $directives;

    /**
     * @var NamedTypeNode[]
     */
    public $types = [];

    /**
     * @var string
     */
    public $description;
}
