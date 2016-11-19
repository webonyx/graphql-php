<?php
namespace GraphQL\Language\AST;

class DirectiveNode extends Node
{
    public $kind = NodeKind::DIRECTIVE;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var ArgumentNode[]
     */
    public $arguments;
}
