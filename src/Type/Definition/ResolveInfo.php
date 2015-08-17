<?php
namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Schema;
use GraphQL\Utils;

class ResolveInfo
{
    /**
     * @var string
     */
    public $fieldName;

    /**
     * @var Field[]
     */
    public $fieldASTs;

    /**
     * @var OutputType
     */
    public $returnType;

    /**
     * @var Type|CompositeType
     */
    public $parentType;

    /**
     * @var Schema
     */
    public $schema;

    /**
     * @var array<fragmentName, FragmentDefinition>
     */
    public $fragments;

    /**
     * @var mixed
     */
    public $rootValue;

    /**
     * @var OperationDefinition
     */
    public $operation;

    /**
     * @var array<variableName, mixed>
     */
    public $variableValues;

    public function __construct(array $values)
    {
        Utils::assign($this, $values);
    }
}
