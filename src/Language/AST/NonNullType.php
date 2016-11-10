<?php

namespace GraphQL\Language\AST;

class NonNullType extends Node implements Type
{
    protected $kind = Node::NON_NULL_TYPE;

    /**
     * @var Name | ListType
     */
    public $type;
}
