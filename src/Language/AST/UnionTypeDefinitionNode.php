<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class UnionTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    public string $kind = NodeKind::UNION_TYPE_DEFINITION;

    /** @var NameNode */
    public $name;

    /** @var NodeList<DirectiveNode> */
    public $directives;

    /** @var NodeList<NamedTypeNode> */
    public $types;

    /** @var StringValueNode|null */
    public $description;
}
