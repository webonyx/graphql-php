<?php
namespace GraphQL\Language\AST;

class ListTypeNode extends Node implements TypeNode
{
    public $kind = NodeType::LIST_TYPE;

    /**
     * @var Node
     */
    public $type;
}
