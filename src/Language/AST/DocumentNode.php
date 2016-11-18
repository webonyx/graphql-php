<?php
namespace GraphQL\Language\AST;

class DocumentNode extends Node
{
    public $kind = NodeType::DOCUMENT;

    /**
     * @var DefinitionNode[]
     */
    public $definitions;
}
