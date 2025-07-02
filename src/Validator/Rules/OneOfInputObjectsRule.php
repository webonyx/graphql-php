<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Validator\QueryValidationContext;

/**
 * OneOf Input Objects validation rule.
 *
 * Validates that OneOf Input Objects have exactly one non-null field provided.
 */
class OneOfInputObjectsRule extends ValidationRule
{
    public function getVisitor(QueryValidationContext $context): array
    {
        return [
            NodeKind::OBJECT => static function (ObjectValueNode $node) use ($context): void {
                $type = $context->getInputType();

                if ($type === null) {
                    return;
                }

                $namedType = Type::getNamedType($type);
                if (! ($namedType instanceof InputObjectType) || ! $namedType->isOneOf()) {
                    return;
                }

                $providedFields = [];
                foreach ($node->fields as $fieldNode) {
                    $fieldName = $fieldNode->name->value;
                    $providedFields[] = $fieldName;
                }

                $fieldCount = count($providedFields);

                if ($fieldCount === 0) {
                    $context->reportError(new Error(
                        static::oneOfInputObjectExpectedExactlyOneFieldMessage($namedType->name),
                        [$node]
                    ));
                } elseif ($fieldCount > 1) {
                    $context->reportError(new Error(
                        static::oneOfInputObjectExpectedExactlyOneFieldMessage($namedType->name, $fieldCount),
                        [$node]
                    ));
                }
            },
        ];
    }

    public static function oneOfInputObjectExpectedExactlyOneFieldMessage(string $typeName, ?int $providedCount = null): string
    {
        if ($providedCount === null) {
            return "OneOf input object '{$typeName}' must specify exactly one field.";
        }

        return "OneOf input object '{$typeName}' must specify exactly one field, but {$providedCount} fields were provided.";
    }
}
