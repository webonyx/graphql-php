<?php

namespace GraphQL\Language\AST;

class Name extends Node implements Type
{
    protected $kind = Node::NAME;

    /**
     * @var string
     */
    public $value;
}
