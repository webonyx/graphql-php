<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error;
use GraphQL\Language\AST\Node;
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
            Node::OPERATION_DEFINITION => function(OperationDefinition $node) use ($context) {
                $operationName = $node->name;

                if ($operationName) {
                    if (!empty($this->knownOperationNames[$operationName->value])) {
                        $context->reportError(new Error(
                            self::duplicateOperationNameMessage($operationName->value),
                            [ $this->knownOperationNames[$operationName->value], $operationName ]
                        ));
                    } else {
                        $this->knownOperationNames[$operationName->value] = $operationName;
                    }
                }
                return false;
            },
            Node::FRAGMENT_DEFINITION => function() {
                return Visitor::skipNode();
            }
        ];
    }
}
