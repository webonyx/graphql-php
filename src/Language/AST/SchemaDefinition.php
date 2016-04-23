<?php
namespace GraphQL\Language\AST;

class SchemaDefinition extends Node implements TypeSystemDefinition
{
    /**
     * @var string
     */
    public $kind = Node::SCHEMA_DEFINITION;

    /**
     * @var OperationTypeDefinition[]
     */
    public $operationTypes;
}
