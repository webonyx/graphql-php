<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Validator\QueryValidationContext;

class NoInvisibleFields extends ValidationRule
{
    public function getVisitor(QueryValidationContext $context): array
    {
        return [
            NodeKind::FIELD => function (FieldNode $node) use ($context): void {
                $fieldDef = $context->getFieldDef();
                if ($fieldDef === null) {
                    return;
                }

                $parentType = $context->getParentType();
                if (! $parentType instanceof NamedType) {
                    return;
                }

                if ($fieldDef->isVisible($context)) {
                    return;
                }

                // Report an error, including helpful suggestions.
                $context->reportError(new Error(
                    "Cannot query field \"{$node->name->value}\" on type \"{$parentType->name}\"."
                ));
            },
        ];
    }
}
