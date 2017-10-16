<?php
namespace GraphQL\Language\AST;

class NameNode extends Node implements TypeNode
{
    public $kind = NodeKind::NAME;

    /**
     * @var string
     */
    public $value;
}
