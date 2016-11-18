<?php
namespace GraphQL\Language\AST;

class EnumValueDefinitionNode extends Node
{
    /**
     * @var string
     */
    public $kind = NodeType::ENUM_VALUE_DEFINITION;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var DirectiveNode[]
     */
    public $directives;
}
