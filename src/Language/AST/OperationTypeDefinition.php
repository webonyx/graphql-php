<?php

namespace GraphQL\Language\AST;

class OperationTypeDefinition extends Node
{
    /**
     * @var string
     */
    protected $kind = NodeType::OPERATION_TYPE_DEFINITION;

    /**
     * One of 'query' | 'mutation' | 'subscription'
     *
     * @var string
     */
    protected $operation;

    /**
     * @var NamedType
     */
    protected $type;

    /**
     * OperationTypeDefinition constructor.
     *
     * @param array     $operation
     * @param NamedType $type
     * @param null      $loc
     */
    public function __construct($operation, NamedType $type, $loc = null)
    {
        $this->operation = $operation;
        $this->type = $type;
        $this->loc = $loc;
    }

    /**
     * @return string
     */
    public function getOperation()
    {
        return $this->operation;
    }

    /**
     * @param string $operation
     *
     * @return OperationTypeDefinition
     */
    public function setOperation($operation)
    {
        $this->operation = $operation;

        return $this;
    }

    /**
     * @return NamedType
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param NamedType $type
     *
     * @return OperationTypeDefinition
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }
}
