<?php
namespace GraphQL\Language\AST;

class NonNullTypeNode extends Node implements TypeNode
{
    public $kind = NodeKind::NON_NULL_TYPE;

    /**
     * @var NameNode | ListTypeNode
     */
    public $type;
}
