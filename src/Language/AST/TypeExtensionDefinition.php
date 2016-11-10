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
    protected $definition;

    /**
     * TypeExtensionDefinition constructor.
     *
     * @param ObjectTypeDefinition $definition
     * @param null                 $loc
     */
    public function __construct(ObjectTypeDefinition $definition, $loc = null)
    {
        $this->definition = $definition;
        $this->loc = $loc;
    }

    /**
     * @return ObjectTypeDefinition
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * @param ObjectTypeDefinition $definition
     *
     * @return TypeExtensionDefinition
     */
    public function setDefinition($definition)
    {
        $this->definition = $definition;

        return $this;
    }
}
