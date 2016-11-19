<?php
namespace GraphQL\Language\AST;

class ListTypeNode extends Node implements TypeNode
{
    public $kind = NodeKind::LIST_TYPE;

    /**
     * @var Node
     */
    public $type;
}
