<?php
namespace GraphQL\Language\AST;

class Name extends Node implements Type
{
    public $kind = NodeType::NAME;

    /**
     * @var string
     */
    public $value;
}
