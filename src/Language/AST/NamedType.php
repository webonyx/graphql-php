<?php

namespace GraphQL\Language\AST;

class NamedType extends Node implements Type
{
    protected $kind = NodeType::NAMED_TYPE;

    /**
     * @var Name
     */
    public $name;
}
