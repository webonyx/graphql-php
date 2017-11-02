<?php
namespace GraphQL\Executor;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Schema;

/**
 * Data that must be available at all points during query execution.
 *
 * Namely, schema of the type system that is currently executing,
 * and the fragments defined in the query document
 *
 * @internal
 */
class ExecutionContext
{
    /**
     * @var Schema
     */
    public $schema;

    /**
     * @var FragmentDefinitionNode[]
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
     * @var OperationDefinitionNode
     */
    public $operation;

    /**
     * @var array
     */
    public $variableValues;

    /**
     * @var callable
     */
    public $fieldResolver;

    /**
     * @var array
     */
    public $errors;

    /**
     * @var array
     */
    public $extensions;

    public function __construct(
        $schema,
        $fragments,
        $root,
        $contextValue,
        $operation,
        $variables,
        $errors,
        $fieldResolver,
        $promiseAdapter,
        $extensions
    )
    {
        $this->schema = $schema;
        $this->fragments = $fragments;
        $this->rootValue = $root;
        $this->contextValue = $contextValue;
        $this->operation = $operation;
        $this->variableValues = $variables;
        $this->errors = $errors ?: [];
        $this->fieldResolver = $fieldResolver;
        $this->promises = $promiseAdapter;
        $this->extensions = $extensions ?: [];
    }

    public function addError(Error $error)
    {
        $this->errors[] = $error;
        return $this;
    }

    public function setExtension($key, $value)
    {
        $this->extensions[$key] = $value;
        return $this;
    }

    public function getExtension($key)
    {
        if(array_key_exists($key, $this->extensions))
        {
          return $this->extensions[$key];
        }
        return null;
    }
}
