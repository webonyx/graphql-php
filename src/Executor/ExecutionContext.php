<?php
namespace GraphQL\Executor;

use GraphQL\Error\Error;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Schema;

/**
 * Data that must be available at all points during query execution.
 *
 * Namely, schema of the type system that is currently executing,
 * and the fragments defined in the query document
 */
class ExecutionContext
{
    /**
     * @var Schema
     */
    public $schema;

    /**
     * @var array<string, FragmentDefinition>
     */
    public $fragments;

    /**
     * @var mixed
     */
    public $rootValue;

    /**
     * @var mixed
     */
    public $contextValue;

    /**
     * @var OperationDefinition
     */
    public $operation;

    /**
     * @var array
     */
    public $variableValues;

    /**
     * @var array
     */
    public $errors;

    /**
     * @var array
     */
    public $memoized = [];

    public function __construct($schema, $fragments, $root, $contextValue, $operation, $variables, $errors)
    {
        $this->schema = $schema;
        $this->fragments = $fragments;
        $this->rootValue = $root;
        $this->contextValue = $contextValue;
        $this->operation = $operation;
        $this->variableValues = $variables;
        $this->errors = $errors ?: [];
    }

    public function addError(Error $error)
    {
        $this->errors[] = $error;
        return $this;
    }
}
