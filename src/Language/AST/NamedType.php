<?php
namespace GraphQL\Language\AST;

class NamedType extends Node implements Type
{
    public $kind = NodeType::NAMED_TYPE;

    /**
     * @var Name
     */
    public $name;
}
