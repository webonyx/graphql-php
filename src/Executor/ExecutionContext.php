<?php declare(strict_types=1);

namespace GraphQL\Executor;

use GraphQL\Error\Error;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Schema;

/**
 * Data that must be available at all points during query execution.
 *
 * Namely, schema of the type system that is currently executing,
 * and the fragments defined in the query document.
 *
 * @phpstan-import-type FieldResolver from Executor
 */
class ExecutionContext
{
    public Schema $schema;

    /** @var array<string, FragmentDefinitionNode> */
    public array $fragments;

    /** @var mixed */
    public $rootValue;

    /** @var mixed */
    public $contextValue;

    public OperationDefinitionNode $operation;

    /** @var array<string, mixed> */
    public array $variableValues;

    /**
     * @var callable
     *
     * @phpstan-var FieldResolver
     */
    public $fieldResolver;

    /** @var array<int, Error> */
    public array $errors;

    public PromiseAdapter $promiseAdapter;

    /**
     * @param array<string, FragmentDefinitionNode> $fragments
     * @param mixed                                 $rootValue
     * @param mixed                                 $contextValue
     * @param array<string, mixed>                  $variableValues
     * @param array<int, Error>                     $errors
     *
     * @phpstan-param FieldResolver $fieldResolver
     */
    public function __construct(
        Schema $schema,
        array $fragments,
        $rootValue,
        $contextValue,
        OperationDefinitionNode $operation,
        array $variableValues,
        array $errors,
        callable $fieldResolver,
        PromiseAdapter $promiseAdapter
    ) {
        $this->schema = $schema;
        $this->fragments = $fragments;
        $this->rootValue = $rootValue;
        $this->contextValue = $contextValue;
        $this->operation = $operation;
        $this->variableValues = $variableValues;
        $this->errors = $errors;
        $this->fieldResolver = $fieldResolver;
        $this->promiseAdapter = $promiseAdapter;
    }

    public function addError(Error $error): void
    {
        $this->errors[] = $error;
    }
}
