<?php
namespace GraphQL\Language\AST;

class Document extends Node
{
    public $kind = NodeType::DOCUMENT;

    /**
     * @var Definition[]
     */
    public $definitions;
}
