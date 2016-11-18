<?php
namespace GraphQL\Language\AST;

class NameNode extends Node implements TypeNode
{
    public $kind = NodeType::NAME;

    /**
     * @var string
     */
    public $value;
}
