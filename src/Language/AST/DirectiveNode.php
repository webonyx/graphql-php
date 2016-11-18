<?php
namespace GraphQL\Language\AST;

class DirectiveNode extends Node
{
    public $kind = NodeType::DIRECTIVE;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var ArgumentNode[]
     */
    public $arguments;
}
