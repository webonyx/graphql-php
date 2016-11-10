<?php

namespace GraphQL\Language\AST;

class ListType extends Node implements Type
{
    protected $kind = NodeType::LIST_TYPE;

    /**
     * @var Node
     */
    public $type;
}
