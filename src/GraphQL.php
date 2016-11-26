<?php
namespace GraphQL;

use GraphQL\Error\Error;
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
     * @param Schema $schema
     * @param $requestString
     * @param mixed $rootValue
     * @param array <string, string>|null $variableValues
     * @param string|null $operationName
     * @return Promise|array
     */
    public static function execute(Schema $schema, $requestString, $rootValue = null, $contextValue = null, $variableValues = null, $operationName = null)
    {
        $result = self::executeAndReturnResult($schema, $requestString, $rootValue, $contextValue, $variableValues, $operationName);

        return $result instanceof ExecutionResult ? $result->toArray() : $result->then(function(ExecutionResult $executionResult) { return $executionResult->toArray(); });
    }

    /**
     * @param Schema $schema
     * @param $requestString
     * @param null $rootValue
     * @param null $variableValues
     * @param null $operationName
     * @return ExecutionResult|Promise
     */
    public static function executeAndReturnResult(Schema $schema, $requestString, $rootValue = null, $contextValue = null, $variableValues = null, $operationName = null)
    {
        try {
            if ($requestString instanceof DocumentNode) {
                $documentNode = $requestString;
            } else {
                $source = new Source($requestString ?: '', 'GraphQL request');
                $documentNode = Parser::parse($source);
            }

            /** @var QueryComplexity $queryComplexity */
            $queryComplexity = DocumentValidator::getRule('QueryComplexity');
            $queryComplexity->setRawVariableValues($variableValues);

            $validationErrors = DocumentValidator::validate($schema, $documentNode);

            if (!empty($validationErrors)) {
                return new ExecutionResult(null, $validationErrors);
            } else {
                return Executor::execute($schema, $documentNode, $rootValue, $contextValue, $variableValues, $operationName);
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
     * @param PromiseAdapter|null $promiseAdapter
     */
    public static function setPromiseAdapter(PromiseAdapter $promiseAdapter = null)
    {
        Executor::setPromiseAdapter($promiseAdapter);
    }
}
