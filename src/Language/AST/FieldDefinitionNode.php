<?php
namespace GraphQL\Language\AST;

class FieldDefinitionNode extends Node
{
    /**
     * @var string
     */
    public $kind = NodeKind::FIELD_DEFINITION;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var InputValueDefinitionNode[]
     */
    public $arguments;

    /**
     * @var TypeNode
     */
    public $type;

    /**
     * @var DirectiveNode[]
     */
    public $directives;

    /**
     * @var string
     */
    public $description;
}
