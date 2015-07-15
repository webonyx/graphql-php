<?php
namespace GraphQL\Language\AST;

class ListType extends Node implements Type
{
    public $kind = Node::LIST_TYPE;

    /**
     * @var Node
     */
    public $type;
}
