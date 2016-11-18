<?php
namespace GraphQL\Language\AST;

class NamedTypeNode extends Node implements TypeNode
{
    public $kind = NodeType::NAMED_TYPE;

    /**
     * @var NameNode
     */
    public $name;
}
