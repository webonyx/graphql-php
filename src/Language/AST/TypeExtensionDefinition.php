<?php

namespace GraphQL\Language\AST;

class TypeExtensionDefinition extends Node implements TypeSystemDefinition
{
    /**
     * @var string
     */
    protected $kind = NodeType::TYPE_EXTENSION_DEFINITION;

    /**
     * @var ObjectTypeDefinition
     */
    public $definition;
}
