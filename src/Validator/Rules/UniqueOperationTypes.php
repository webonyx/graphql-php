<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaTypeExtensionNode;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\SDLValidationContext;

/**
 * Unique operation types
 *
 * A GraphQL document is only valid if it has only one type per operation.
 */
class UniqueOperationTypes extends ValidationRule
{
    public function getSDLVisitor(SDLValidationContext $context): array
    {
        $schema                 = $context->getSchema();
        $definedOperationTypes  = [];
        $existingOperationTypes = $schema !== null
            ? [
                'query' => $schema->getQueryType(),
                'mutation' => $schema->getMutationType(),
                'subscription' => $schema->getSubscriptionType(),
            ]
            : [];

        /**
         * @param SchemaDefinitionNode|SchemaTypeExtensionNode $node
         */
        $checkOperationTypes = static function ($node) use ($context, &$definedOperationTypes, $existingOperationTypes): VisitorOperation {
            $operationTypesNodes = $node->operationTypes;
            foreach ($operationTypesNodes as $operationType) {
                $operation                    = $operationType->operation;
                $alreadyDefinedOperationType  = $definedOperationTypes[$operation] ?? null;
                $alreadyExistingOperationType = $existingOperationTypes[$operation] ?? null;

                if ($alreadyExistingOperationType !== null) {
                    $context->reportError(
                        new Error(
                            "Type for ${operation} already defined in the schema. It cannot be redefined.",
                            $operationType,
                        ),
                    );
                } elseif ($alreadyDefinedOperationType !== null) {
                    $context->reportError(
                        new Error(
                            "There can be only one ${operation} type in schema.",
                            [$alreadyDefinedOperationType, $operationType],
                        ),
                    );
                } else {
                    $definedOperationTypes[$operation] = $operationType;
                }
            }

            return Visitor::skipNode();
        };

        return [
            NodeKind::SCHEMA_DEFINITION => $checkOperationTypes,
            NodeKind::SCHEMA_EXTENSION => $checkOperationTypes,
        ];
    }
}
