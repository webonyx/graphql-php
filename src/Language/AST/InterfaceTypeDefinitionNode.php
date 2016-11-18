<?php
namespace GraphQL\Language\AST;

class InterfaceTypeDefinitionNode extends Node implements TypeDefinitionNode
{
    /**
     * @var string
     */
    public $kind = NodeType::INTERFACE_TYPE_DEFINITION;

    /**
     * @var NameNode
     */
    public $name;

    /**
     * @var DirectiveNode[]
     */
    public $directives;

    /**
     * @var FieldDefinitionNode[]
     */
    public $fields = [];
}
