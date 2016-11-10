<?php

namespace GraphQL\Language\AST;

class SchemaDefinition extends Node implements TypeSystemDefinition
{
    /**
     * @var string
     */
    protected $kind = NodeType::SCHEMA_DEFINITION;

    /**
     * @var Directive[]
     */
    protected $directives;

    /**
     * @var OperationTypeDefinition[]
     */
    protected $operationTypes;

    /**
     * SchemaDefinition constructor.
     *
     * @param array $directives
     * @param array $operationTypes
     * @param null  $loc
     */
    public function __construct(array $directives, array $operationTypes, $loc = null)
    {
        $this->directives = $directives;
        $this->operationTypes = $operationTypes;
        $this->loc = $loc;
    }

    /**
     * @return Directive[]
     */
    public function getDirectives()
    {
        return $this->directives;
    }

    /**
     * @param Directive[] $directives
     *
     * @return SchemaDefinition
     */
    public function setDirectives($directives)
    {
        $this->directives = $directives;

        return $this;
    }

    /**
     * @return OperationTypeDefinition[]
     */
    public function getOperationTypes()
    {
        return $this->operationTypes;
    }

    /**
     * @param OperationTypeDefinition[] $operationTypes
     *
     * @return SchemaDefinition
     */
    public function setOperationTypes($operationTypes)
    {
        $this->operationTypes = $operationTypes;

        return $this;
    }
}
