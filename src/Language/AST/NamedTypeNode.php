<?php
namespace GraphQL\Language\AST;

class NamedTypeNode extends Node implements TypeNode
{
    public $kind = NodeKind::NAMED_TYPE;

    /**
     * @var NameNode
     */
    public $name;
}
