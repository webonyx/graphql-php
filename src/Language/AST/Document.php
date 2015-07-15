<?php
namespace GraphQL\Language\AST;

class Document extends Node
{
    public $kind = Node::DOCUMENT;

    /**
     * @var array<Definition>
     */
    public $definitions;
}
