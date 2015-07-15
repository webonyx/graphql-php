<?php
namespace GraphQL\Language\AST;

class NonNullType extends Node implements Type
{
    public $kind = Node::NON_NULL_TYPE;

    /**
     * @var Name | ListType
     */
    public $type;
}
