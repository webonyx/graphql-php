<?php
namespace GraphQL;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Validator\DocumentValidator;

class GraphQL
{
    /**
     * @param Schema $schema
     * @param $requestString
     * @param mixed $rootValue
     * @param array <string, string>|null $variableValues
     * @param string|null $operationName
     * @return array
     */
    public static function execute(Schema $schema, $requestString, $rootValue = null, $variableValues = null, $operationName = null)
    {
        return self::executeAndReturnResult($schema, $requestString, $rootValue, $variableValues, $operationName)->toArray();
    }

    /**
     * @param Schema $schema
     * @param $requestString
     * @param null $rootValue
     * @param null $variableValues
     * @param null $operationName
     * @return array|ExecutionResult
     */
    public static function executeAndReturnResult(Schema $schema, $requestString, $rootValue = null, $variableValues = null, $operationName = null)
    {
        try {
            $source = new Source($requestString ?: '', 'GraphQL request');
            $documentAST = Parser::parse($source);
            $validationErrors = DocumentValidator::validate($schema, $documentAST);

            if (!empty($validationErrors)) {
                return new ExecutionResult(null, $validationErrors);
            } else {
                return Executor::execute($schema, $documentAST, $rootValue, $variableValues, $operationName);
            }
        } catch (Error $e) {
            return new ExecutionResult(null, [$e]);
        }
    }
}
