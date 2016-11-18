<?php
namespace GraphQL\Language\AST;

class NullValue extends Node implements Value
{
    public $kind = NodeType::NULL;
}
