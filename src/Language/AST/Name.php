<?php

namespace GraphQL\Language\AST;

class Name extends Node implements Type
{
    protected $kind = NodeType::NAME;

    /**
     * @var string
     */
    public $value;
}
