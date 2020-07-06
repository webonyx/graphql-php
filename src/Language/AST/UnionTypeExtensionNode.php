<?php

declare(strict_types=1);

namespace GraphQL\Language\AST;

class UnionTypeExtensionNode extends Node implements TypeExtensionNode
{
    /** @var string */
    public $kind = NodeKind::UNION_TYPE_EXTENSION;

    /** @var NameNode */
    public $name;

    /** @var NodeList<DirectiveNode>|null */
    public $directives;

    /**
     * TODO: is this actually a NodeList<NamedTypeNode>?
     *
     * @var NamedTypeNode[]|null
     */
    public $types;
}
