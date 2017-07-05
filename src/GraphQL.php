<?php
namespace GraphQL;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Type\Definition\Directive;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;

class GraphQL
{
    /**
     * This is the primary entry point function for fulfilling GraphQL operations
     * by parsing, validating, and executing a GraphQL document along side a
     * GraphQL schema.
     *
     * More sophisticated GraphQL servers, such as those which persist queries,
     * may wish to separate the validation and execution phases to a static time
     * tooling step, and a server runtime step.
     *
     * schema:
     *    The GraphQL type system to use when validating and executing a query.
     * requestString:
     *    A GraphQL language formatted string representing the requested operation.
     * rootValue:
     *    The value provided as the first argument to resolver functions on the top
     *    level type (e.g. the query object type).
     * variableValues:
     *    A mapping of variable name to runtime value to use for all variables
     *    defined in the requestString.
     * operationName:
     *    The name of the operation to use if requestString contains multiple
     *    possible operations. Can be omitted if requestString contains only
     *    one operation.
     * fieldResolver:
     *    A resolver function to use when one is not provided by the schema.
     *    If not provided, the default field resolver is used (which looks for a
     *    value or method on the source value with the field's name).
     *
     * @param Schema $schema
     * @param string|DocumentNode $source
     * @param mixed $rootValue
     * @param array|null $variableValues
     * @param string|null $operationName
     * @param callable $fieldResolver
     * @return Promise|array
     */
    public static function execute(
        Schema $schema,
        $source,
        $rootValue = null,
        $contextValue = null,
        $variableValues = null,
        $operationName = null,
        $fieldResolver = null
    )
    {
        $result = self::executeAndReturnResult(
            $schema,
            $source,
            $rootValue,
            $contextValue,
            $variableValues,
            $operationName,
            $fieldResolver
        );

        if ($result instanceof ExecutionResult) {
            return $result->toArray();
        }
        if ($result instanceof Promise) {
            return $result->then(function(ExecutionResult $executionResult) {
                return $executionResult->toArray();
            });
        }
        throw new InvariantViolation("Unexpected execution result");
    }

    /**
     * Same as `execute`, but returns instance of ExecutionResult instead of array,
     * which can be used for custom error formatting or adding extensions to response
     *
     * @param Schema $schema
     * @param string|DocumentNode $source
     * @param mixed $rootValue
     * @param array|null $variableValues
     * @param string|null $operationName
     * @param callable $fieldResolver
     * @return ExecutionResult|Promise
     */
    public static function executeAndReturnResult(
        Schema $schema,
        $source,
        $rootValue = null,
        $contextValue = null,
        $variableValues = null,
        $operationName = null,
        $fieldResolver = null
    )
    {
        try {
            if ($source instanceof DocumentNode) {
                $documentNode = $source;
            } else {
                $documentNode = Parser::parse(new Source($source ?: '', 'GraphQL'));
            }

            /** @var QueryComplexity $queryComplexity */
            $queryComplexity = DocumentValidator::getRule('QueryComplexity');
            $queryComplexity->setRawVariableValues($variableValues);

            $validationErrors = DocumentValidator::validate($schema, $documentNode);

            if (!empty($validationErrors)) {
                return new ExecutionResult(null, $validationErrors);
            } else {
                return Executor::execute(
                    $schema,
                    $documentNode,
                    $rootValue,
                    $contextValue,
                    $variableValues,
                    $operationName,
                    $fieldResolver
                );
            }
        } catch (Error $e) {
            return new ExecutionResult(null, [$e]);
        }
    }

    /**
     * @return array
     */
    public static function getInternalDirectives()
    {
        return array_values(Directive::getInternalDirectives());
    }

    /**
     * @param callable $fn
     */
    public static function setDefaultFieldResolver(callable $fn)
    {
        Executor::setDefaultFieldResolver($fn);
    }

    /**
     * @param PromiseAdapter|null $promiseAdapter
     */
    public static function setPromiseAdapter(PromiseAdapter $promiseAdapter = null)
    {
        Executor::setPromiseAdapter($promiseAdapter);
    }
}
