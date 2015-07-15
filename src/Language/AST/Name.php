<?php
namespace GraphQL\Language\AST;

class Name extends Node implements Type
{
    public $kind = Node::NAME;

    /**
     * @var string
     */
    public $value;
}
