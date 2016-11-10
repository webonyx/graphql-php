<?php
namespace GraphQL\Language\AST;

class ListType extends Node implements Type
{
    public $kind = NodeType::LIST_TYPE;

    /**
     * @var Node
     */
    public $type;
}
