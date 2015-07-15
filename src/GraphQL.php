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
     * @param mixed $rootObject
     * @param array <string, string>|null $variableValues
     * @param string|null $operationName
     * @return array
     */
    public static function execute(Schema $schema, $requestString, $rootObject = null, $variableValues = null, $operationName = null)
    {
        try {
            $source = new Source($requestString ?: '', 'GraphQL request');
            $ast = Parser::parse($source);
            $validationResult = DocumentValidator::validate($schema, $ast);

            if (empty($validationResult['isValid'])) {
                return ['errors' => $validationResult['errors']];
            } else {
                return Executor::execute($schema, $rootObject, $ast, $operationName, $variableValues);
            }
        } catch (\Exception $e) {
            return ['errors' => Error::formatError($e)];
        }
    }
}
