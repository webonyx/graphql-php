<?php
namespace GraphQL;

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
        try {
            $source = new Source($requestString ?: '', 'GraphQL request');
            $documentAST = Parser::parse($source);
            $validationErrors = DocumentValidator::validate($schema, $documentAST);

            if (!empty($validationErrors)) {
                return ['errors' => array_map(function($e)
                {
                    $errorClass = $e instanceof Error ? get_class($e):'\GraphQL\Error';
                    return $errorClass::formatError($e);
                }, $validationErrors)];
            } else {
                return Executor::execute($schema, $documentAST, $rootValue, $variableValues, $operationName)->toArray();
            }
        } catch (Error $e) {
            $errorClass = get_class($e);
            return ['errors' => [$errorClass::formatError($e)]];
        }
    }
}
