<?php

namespace GraphQL\Language\AST;

class NamedType extends Node implements Type
{
    protected $kind = Node::NAMED_TYPE;

    /**
     * @var Name
     */
    public $name;
}
