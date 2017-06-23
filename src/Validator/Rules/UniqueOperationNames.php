<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Visitor;
use GraphQL\Validator\ValidationContext;

class UniqueOperationNames
{
    public static function duplicateOperationNameMessage($operationName)
    {
        return "There can only be one operation named \"$operationName\".";
    }

    public $knownOperationNames;

    public function __invoke(ValidationContext $context)
    {
        $this->knownOperationNames = [];

        return [
            NodeKind::OPERATION_DEFINITION => function (OperationDefinitionNode $node) use ($context) {
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
                return Visitor::skipNode();
            },
            NodeKind::FRAGMENT_DEFINITION => function () {
                return Visitor::skipNode();
            }
        ];
    }
}
