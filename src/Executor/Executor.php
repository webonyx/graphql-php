<?php declare(strict_types=1);

namespace GraphQL\Executor;

use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;

/**
 * Implements the "Evaluating requests" section of the GraphQL specification.
 *
 * @phpstan-type FieldResolver callable(mixed, array<string, mixed>, mixed, ResolveInfo): mixed
 * @phpstan-type ImplementationFactory callable(PromiseAdapter, Schema, DocumentNode, mixed, mixed, array<mixed>, ?string, callable): ExecutorImplementation
 *
 * @see \GraphQL\Tests\Executor\ExecutorTest
 */
class Executor
{
    /**
     * @var callable
     *
     * @phpstan-var FieldResolver
     */
    private static $defaultFieldResolver = [self::class, 'defaultFieldResolver'];

    private static ?PromiseAdapter $defaultPromiseAdapter;

    /**
     * @var callable
     *
     * @phpstan-var ImplementationFactory
     */
    private static $implementationFactory = [ReferenceExecutor::class, 'create'];

    /** @phpstan-return FieldResolver */
    public static function getDefaultFieldResolver(): callable
    {
        return self::$defaultFieldResolver;
    }

    /**
     * Set a custom default resolve function.
     *
     * @phpstan-param FieldResolver $fieldResolver
     */
    public static function setDefaultFieldResolver(callable $fieldResolver): void
    {
        self::$defaultFieldResolver = $fieldResolver;
    }

    public static function getPromiseAdapter(): PromiseAdapter
    {
        return self::$defaultPromiseAdapter ??= new SyncPromiseAdapter();
    }

    /** Set a custom default promise adapter. */
    public static function setPromiseAdapter(PromiseAdapter $defaultPromiseAdapter = null): void
    {
        self::$defaultPromiseAdapter = $defaultPromiseAdapter;
    }

    /** @phpstan-return ImplementationFactory */
    public static function getImplementationFactory(): callable
    {
        return self::$implementationFactory;
    }

    /**
     * Set a custom executor implementation factory.
     *
     * @phpstan-param ImplementationFactory $implementationFactory
     */
    public static function setImplementationFactory(callable $implementationFactory): void
    {
        self::$implementationFactory = $implementationFactory;
    }

    /**
     * Executes DocumentNode against given $schema.
     *
     * Always returns ExecutionResult and never throws.
     * All errors which occur during operation execution are collected in `$result->errors`.
     *
     * @param mixed                     $rootValue
     * @param mixed                     $contextValue
     * @param array<string, mixed>|null $variableValues
     *
     * @phpstan-param FieldResolver|null $fieldResolver
     *
     * @api
     *
     * @throws InvariantViolation
     */
    public static function execute(
        Schema $schema,
        DocumentNode $documentNode,
        $rootValue = null,
        $contextValue = null,
        array $variableValues = null,
        string $operationName = null,
        callable $fieldResolver = null
    ): ExecutionResult {
        $promiseAdapter = new SyncPromiseAdapter();

        $result = static::promiseToExecute(
            $promiseAdapter,
            $schema,
            $documentNode,
            $rootValue,
            $contextValue,
            $variableValues,
            $operationName,
            $fieldResolver
        );

        return $promiseAdapter->wait($result);
    }

    /**
     * Same as execute(), but requires promise adapter and returns a promise which is always
     * fulfilled with an instance of ExecutionResult and never rejected.
     *
     * Useful for async PHP platforms.
     *
     * @param mixed                     $rootValue
     * @param mixed                     $contextValue
     * @param array<string, mixed>|null $variableValues
     *
     * @phpstan-param FieldResolver|null $fieldResolver
     *
     * @api
     */
    public static function promiseToExecute(
        PromiseAdapter $promiseAdapter,
        Schema $schema,
        DocumentNode $documentNode,
        $rootValue = null,
        $contextValue = null,
        array $variableValues = null,
        string $operationName = null,
        callable $fieldResolver = null
    ): Promise {
        $executor = (self::$implementationFactory)(
            $promiseAdapter,
            $schema,
            $documentNode,
            $rootValue,
            $contextValue,
            $variableValues ?? [],
            $operationName,
            $fieldResolver ?? self::$defaultFieldResolver
        );

        return $executor->doExecute();
    }

    /**
     * If a resolve function is not given, then a default resolve behavior is used
     * which takes the property of the root value of the same name as the field
     * and returns it as the result, or if it's a function, returns the result
     * of calling that function while passing along args and context.
     *
     * @param mixed $objectLikeValue
     * @param array<string, mixed> $args
     * @param mixed $contextValue
     *
     * @return mixed
     */
    public static function defaultFieldResolver($objectLikeValue, array $args, $contextValue, ResolveInfo $info)
    {
        $property = Utils::extractKey($objectLikeValue, $info->fieldName);

        return $property instanceof \Closure
            ? $property($objectLikeValue, $args, $contextValue, $info)
            : $property;
    }
}
