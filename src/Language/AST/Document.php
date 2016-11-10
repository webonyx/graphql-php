<?php

namespace GraphQL\Language\AST;

class Document extends Node
{
    protected $kind = Node::DOCUMENT;

    /**
     * @var Definition[]
     */
    public $definitions;
}
