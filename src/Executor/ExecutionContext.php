<?php
namespace GraphQL\Executor;

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
     * @var
     */
    public $root;

    /**
     * @var OperationDefinition
     */
    public $operation;

    /**
     * @var array
     */
    public $variables;

    /**
     * @var array
     */
    public $errors;

    public function __construct($schema, $fragments, $root, $operation, $variables, $errors)
    {
        $this->schema = $schema;
        $this->fragments = $fragments;
        $this->root = $root;
        $this->operation = $operation;
        $this->variables = $variables;
        $this->errors = $errors ?: [];
    }

    public function addError($error)
    {
        $this->errors[] = $error;
        return $this;
    }
}
