<?php
namespace GraphQL\Language\AST;

class TypeExtensionDefinition extends Node implements TypeSystemDefinition
{
    /**
     * @var string
     */
    public $kind = Node::TYPE_EXTENSION_DEFINITION;

    /**
     * @var ObjectTypeDefinition
     */
    public $definition;
}
