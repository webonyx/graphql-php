<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\HasFieldsType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ValidationContext;

use function array_keys;
use function array_merge;
use function arsort;
use function sprintf;

/**
 * @phpstan-import-type AbstractTypeAlias from AbstractType
 */
class FieldsOnCorrectType extends ValidationRule
{
    public function getVisitor(ValidationContext $context): array
    {
        return [
            NodeKind::FIELD => function (FieldNode $node) use ($context): void {
                $fieldDef = $context->getFieldDef();
                if ($fieldDef !== null) {
                    return;
                }

                $type = $context->getParentType();
                if (! $type instanceof NamedType) {
                    return;
                }

                // This isn't valid. Let's find suggestions, if any.
                $schema    = $context->getSchema();
                $fieldName = $node->name->value;
                // First determine if there are any suggested types to condition on.
                $suggestedTypeNames = $this->getSuggestedTypeNames($schema, $type, $fieldName);
                // If there are no suggested types, then perhaps this was a typo?
                $suggestedFieldNames = $suggestedTypeNames === []
                    ? $this->getSuggestedFieldNames($type, $fieldName)
                    : [];

                // Report an error, including helpful suggestions.
                $context->reportError(new Error(
                    static::undefinedFieldMessage(
                        $node->name->value,
                        $type->name,
                        $suggestedTypeNames,
                        $suggestedFieldNames
                    ),
                    [$node]
                ));
            },
        ];
    }

    /**
     * Go through all implementations of a type, as well as the interfaces
     * that it implements. If any of those types include the provided field,
     * suggest them, sorted by how often the type is referenced, starting
     * with interfaces.
     *
     * @return array<int, string>
     */
    protected function getSuggestedTypeNames(Schema $schema, Type $type, string $fieldName): array
    {
        if (Type::isAbstractType($type)) {
            /** @phpstan-var AbstractTypeAlias $type proven by Type::isAbstractType() */

            $suggestedObjectTypes = [];
            $interfaceUsageCount  = [];

            foreach ($schema->getPossibleTypes($type) as $possibleType) {
                if (! $possibleType->hasField($fieldName)) {
                    continue;
                }

                // This object type defines this field.
                $suggestedObjectTypes[] = $possibleType->name;
                foreach ($possibleType->getInterfaces() as $possibleInterface) {
                    if (! $possibleInterface->hasField($fieldName)) {
                        continue;
                    }

                    // This interface type defines this field.
                    $interfaceUsageCount[$possibleInterface->name] =
                        ! isset($interfaceUsageCount[$possibleInterface->name])
                            ? 0
                            : $interfaceUsageCount[$possibleInterface->name] + 1;
                }
            }

            // Suggest interface types based on how common they are.
            arsort($interfaceUsageCount);
            $suggestedInterfaceTypes = array_keys($interfaceUsageCount);

            // Suggest both interface and object types.
            return array_merge($suggestedInterfaceTypes, $suggestedObjectTypes);
        }

        // Otherwise, must be an Object type, which does not have suggested types.
        return [];
    }

    /**
     * For the field name provided, determine if there are any similar field names
     * that may be the result of a typo.
     *
     * @return array<int, string>
     */
    protected function getSuggestedFieldNames(Type $type, string $fieldName): array
    {
        if ($type instanceof HasFieldsType) {
            return Utils::suggestionList(
                $fieldName,
                $type->getFieldNames()
            );
        }

        // Otherwise, must be a Union type, which does not define fields.
        return [];
    }

    /**
     * @param string   $fieldName
     * @param string   $type
     * @param string[] $suggestedTypeNames
     * @param string[] $suggestedFieldNames
     */
    public static function undefinedFieldMessage(
        $fieldName,
        $type,
        array $suggestedTypeNames,
        array $suggestedFieldNames
    ): string {
        $message = sprintf('Cannot query field "%s" on type "%s".', $fieldName, $type);

        if ($suggestedTypeNames !== []) {
            $suggestions = Utils::quotedOrList($suggestedTypeNames);

            $message .= sprintf(' Did you mean to use an inline fragment on %s?', $suggestions);
        } elseif ($suggestedFieldNames !== []) {
            $suggestions = Utils::quotedOrList($suggestedFieldNames);

            $message .= sprintf(' Did you mean %s?', $suggestions);
        }

        return $message;
    }
}
