<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\ValidationContext;
use function array_splice;
use function count;
use function sprintf;

class SingleFieldSubscription extends ValidationRule
{
    /**
     * @return array<string, callable>
     */
    public function getVisitor(ValidationContext $context) : array
    {
        return [
            NodeKind::OPERATION_DEFINITION => static function (OperationDefinitionNode $node) use ($context) : VisitorOperation {
                if ($node->operation === 'subscription') {
                    $selections = $node->selectionSet->selections;

                    if (count($selections) !== 1) {
                        if ($selections instanceof NodeList) {
                            $offendingSelections = $selections->splice(1, count($selections));
                        } else {
                            $offendingSelections = array_splice($selections, 1);
                        }

                        $context->reportError(new Error(
                            self::multipleFieldsInOperation($node->name->value ?? null),
                            $offendingSelections
                        ));
                    }
                }

                return Visitor::skipNode();
            },
        ];
    }

    public static function multipleFieldsInOperation(?string $operationName) : string
    {
        if ($operationName === null) {
            return sprintf('Anonymous Subscription must select only one top level field.');
        }

        return sprintf('Subscription "%s" must select only one top level field.', $operationName);
    }
}
