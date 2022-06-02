<?php declare(strict_types=1);

namespace GraphQL\Language\AST;

class NamedTypeNode extends Node implements TypeNode
{
    public string $kind = NodeKind::NAMED_TYPE;

    public NameNode $name;
}
