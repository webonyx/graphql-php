<?php
namespace GraphQL\Language\AST;

class TypeExtensionDefinitionNode extends Node implements TypeSystemDefinitionNode
{
    /**
     * @var string
     */
    public $kind = NodeType::TYPE_EXTENSION_DEFINITION;

    /**
     * @var ObjectTypeDefinitionNode
     */
    public $definition;
}
