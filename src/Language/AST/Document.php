<?php

namespace GraphQL\Language\AST;

class Document extends Node
{
    protected $kind = NodeType::DOCUMENT;

    /**
     * @var Definition[]
     */
    public $definitions;
}
