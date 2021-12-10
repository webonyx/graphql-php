<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ValidationContext;
use Throwable;

use function array_keys;
use function array_map;
use function count;
use function sprintf;

/**
 * Value literals of correct type.
 *
 * A GraphQL document is only valid if all value literals are of the type
 * expected at their position.
 */
class ValuesOfCorrectType extends ValidationRule
{
    public function getVisitor(ValidationContext $context): array
    {
        $fieldName = '';

        return [
            NodeKind::FIELD        => [
                'enter' => static function (FieldNode $node) use (&$fieldName): void {
                    $fieldName = $node->name->value;
                },
            ],
            NodeKind::NULL         => static function (NullValueNode $node) use ($context, &$fieldName): void {
                $type = $context->getInputType();
                if (! ($type instanceof NonNull)) {
                    return;
                }

                $context->reportError(
                    new Error(
                        static::getBadValueMessage((string) $type, Printer::doPrint($node), null, $context, $fieldName),
                        $node
                    )
                );
            },
            NodeKind::LST          => function (ListValueNode $node) use ($context, &$fieldName): ?VisitorOperation {
                // Note: TypeInfo will traverse into a list's item type, so look to the
                // parent input type to check if it is a list.
                $parentType = $context->getParentInputType();
                $type       = $parentType === null
                    ? null
                    : Type::getNullableType($parentType);
                if (! $type instanceof ListOfType) {
                    $this->isValidScalar($context, $node, $fieldName);

                    return Visitor::skipNode();
                }

                return null;
            },
            NodeKind::OBJECT       => function (ObjectValueNode $node) use ($context, &$fieldName): ?VisitorOperation {
                // Note: TypeInfo will traverse into a list's item type, so look to the
                // parent input type to check if it is a list.
                $type = Type::getNamedType($context->getInputType());
                if (! $type instanceof InputObjectType) {
                    $this->isValidScalar($context, $node, $fieldName);

                    return Visitor::skipNode();
                }

                unset($fieldName);
                // Ensure every required field exists.
                $inputFields = $type->getFields();

                $fieldNodeMap = [];
                foreach ($node->fields as $field) {
                    $fieldNodeMap[$field->name->value] = $field;
                }

                foreach ($inputFields as $fieldName => $fieldDef) {
                    $fieldType = $fieldDef->getType();
                    if (isset($fieldNodeMap[$fieldName]) || ! $fieldDef->isRequired()) {
                        continue;
                    }

                    $context->reportError(
                        new Error(
                            static::requiredFieldMessage($type->name, $fieldName, (string) $fieldType),
                            $node
                        )
                    );
                }

                return null;
            },
            NodeKind::OBJECT_FIELD => static function (ObjectFieldNode $node) use ($context): void {
                $parentType = Type::getNamedType($context->getParentInputType());
                if (! $parentType instanceof InputObjectType) {
                    return;
                }

                if ($context->getInputType() !== null) {
                    return;
                }

                $suggestions = Utils::suggestionList(
                    $node->name->value,
                    array_keys($parentType->getFields())
                );
                $didYouMean  = count($suggestions) > 0
                    ? 'Did you mean ' . Utils::orList($suggestions) . '?'
                    : null;

                $context->reportError(
                    new Error(
                        static::unknownFieldMessage($parentType->name, $node->name->value, $didYouMean),
                        $node
                    )
                );
            },
            NodeKind::ENUM         => function (EnumValueNode $node) use ($context, &$fieldName): void {
                $type = Type::getNamedType($context->getInputType());
                if (! $type instanceof EnumType) {
                    $this->isValidScalar($context, $node, $fieldName);
                } elseif ($type->getValue($node->value) === null) {
                    $context->reportError(
                        new Error(
                            static::getBadValueMessage(
                                $type->name,
                                Printer::doPrint($node),
                                $this->enumTypeSuggestion($type, $node),
                                $context,
                                $fieldName
                            ),
                            $node
                        )
                    );
                }
            },
            NodeKind::INT          => function (IntValueNode $node) use ($context, &$fieldName): void {
                $this->isValidScalar($context, $node, $fieldName);
            },
            NodeKind::FLOAT        => function (FloatValueNode $node) use ($context, &$fieldName): void {
                $this->isValidScalar($context, $node, $fieldName);
            },
            NodeKind::STRING       => function (StringValueNode $node) use ($context, &$fieldName): void {
                $this->isValidScalar($context, $node, $fieldName);
            },
            NodeKind::BOOLEAN      => function (BooleanValueNode $node) use ($context, &$fieldName): void {
                $this->isValidScalar($context, $node, $fieldName);
            },
        ];
    }

    public static function badValueMessage(string $typeName, string $valueName, ?string $message = null)
    {
        return "Expected type {$typeName}, found {$valueName}"
            . ($message
                ? "; ${message}"
                : '.');
    }

    /**
     * @param VariableNode|NullValueNode|IntValueNode|FloatValueNode|StringValueNode|BooleanValueNode|EnumValueNode|ListValueNode|ObjectValueNode $node
     */
    protected function isValidScalar(ValidationContext $context, ValueNode $node, string $fieldName): void
    {
        // Report any error at the full type expected by the location.
        /** @var ScalarType|EnumType|InputObjectType|ListOfType<Type&InputType>|NonNull|null $locationType */
        $locationType = $context->getInputType();

        if ($locationType === null) {
            return;
        }

        $type = Type::getNamedType($locationType);

        if (! $type instanceof ScalarType) {
            $context->reportError(
                new Error(
                    static::getBadValueMessage(
                        (string) $locationType,
                        Printer::doPrint($node),
                        $this->enumTypeSuggestion($type, $node),
                        $context,
                        $fieldName
                    ),
                    $node
                )
            );

            return;
        }

        // Scalars determine if a literal value is valid via parseLiteral() which
        // may throw to indicate failure.
        try {
            $type->parseLiteral($node);
        } catch (Throwable $error) {
            // Ensure a reference to the original error is maintained.
            $context->reportError(
                new Error(
                    static::getBadValueMessage(
                        (string) $locationType,
                        Printer::doPrint($node),
                        $error->getMessage(),
                        $context,
                        $fieldName
                    ),
                    $node,
                    null,
                    [],
                    null,
                    $error
                )
            );
        }
    }

    /**
     * @param VariableNode|NullValueNode|IntValueNode|FloatValueNode|StringValueNode|BooleanValueNode|EnumValueNode|ListValueNode|ObjectValueNode $node
     */
    protected function enumTypeSuggestion(Type $type, ValueNode $node): ?string
    {
        if ($type instanceof EnumType) {
            $suggestions = Utils::suggestionList(
                Printer::doPrint($node),
                array_map(
                    static fn (EnumValueDefinition $value): string => $value->name,
                    $type->getValues()
                )
            );

            return $suggestions === []
                ? null
                : 'Did you mean the enum value ' . Utils::orList($suggestions) . '?';
        }

        return null;
    }

    public static function badArgumentValueMessage(string $typeName, string $valueName, string $fieldName, string $argName, ?string $message = null): string
    {
        return "Field \"{$fieldName}\" argument \"{$argName}\" requires type {$typeName}, found {$valueName}"
            . ($message
                ? "; {$message}"
                : '.'
            );
    }

    public static function requiredFieldMessage(string $typeName, string $fieldName, string $fieldTypeName): string
    {
        return "Field {$typeName}.{$fieldName} of required type {$fieldTypeName} was not provided.";
    }

    public static function unknownFieldMessage(string $typeName, string $fieldName, ?string $message = null): string
    {
        return "Field \"{$fieldName}\" is not defined by type {$typeName}"
            . ($message
                ? "; {$message}"
                : '.'
            );
    }

    protected static function getBadValueMessage(
        string $typeName,
        string $valueName,
        ?string $message = null,
        ?ValidationContext $context = null,
        ?string $fieldName = null
    ): string {
        if ($context !== null) {
            $arg = $context->getArgument();
            if ($arg !== null) {
                return static::badArgumentValueMessage($typeName, $valueName, $fieldName, $arg->name, $message);
            }
        }

        return static::badValueMessage($typeName, $valueName, $message);
    }
}
