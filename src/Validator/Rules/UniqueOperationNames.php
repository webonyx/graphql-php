<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\Visitor;
use GraphQL\Validator\ValidationContext;

class UniqueOperationNames
{
    static function duplicateOperationNameMessage($operationName)
    {
      return "There can only be one operation named \"$operationName\".";
    }

    public $knownOperationNames;

    public function __invoke(ValidationContext $context)
    {
        $this->knownOperationNames = [];

        return [
            NodeType::OPERATION_DEFINITION => function(OperationDefinition $node) use ($context) {
                $operationName = $node->getName();

                if ($operationName) {
                    if (!empty($this->knownOperationNames[$operationName->getValue()])) {
                        $context->reportError(new Error(
                            self::duplicateOperationNameMessage($operationName->getValue()),
                            [ $this->knownOperationNames[$operationName->getValue()], $operationName ]
                        ));
                    } else {
                        $this->knownOperationNames[$operationName->getValue()] = $operationName;
                    }
                }
                return Visitor::skipNode();
            },
            NodeType::FRAGMENT_DEFINITION => function() {
                return Visitor::skipNode();
            }
        ];
    }
}
