<?php

namespace GraphQL\Language\AST;

class ListType extends Node implements Type
{
    protected $kind = Node::LIST_TYPE;

    /**
     * @var Node
     */
    public $type;
}
