<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\QueryValidationContext;

class UniqueOperationNames extends ValidationRule
{
    /** @var array<string, NameNode> */
    protected array $knownOperationNames;

    public function getVisitor(QueryValidationContext $context): array
    {
        $this->knownOperationNames = [];

        return [
            NodeKind::OPERATION_DEFINITION => function (OperationDefinitionNode $node) use ($context): VisitorOperation {
                $operationName = $node->name;

                if ($operationName !== null) {
                    if (! isset($this->knownOperationNames[$operationName->value])) {
                        $this->knownOperationNames[$operationName->value] = $operationName;
                    } else {
                        $context->reportError(new Error(
                            static::duplicateOperationNameMessage($operationName->value),
                            [$this->knownOperationNames[$operationName->value], $operationName]
                        ));
                    }
                }

                return Visitor::skipNode();
            },
            NodeKind::FRAGMENT_DEFINITION => static function (): VisitorOperation {
                return Visitor::skipNode();
            },
        ];
    }

    public static function duplicateOperationNameMessage(string $operationName): string
    {
        return "There can be only one operation named \"{$operationName}\".";
    }
}
