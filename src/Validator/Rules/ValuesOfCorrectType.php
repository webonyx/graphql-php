<?php
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
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ValidationContext;

/**
 * Value literals of correct type
 *
 * A GraphQL document is only valid if all value literals are of the type
 * expected at their position.
 */
class ValuesOfCorrectType extends AbstractValidationRule
{
    static function badValueMessage($typeName, $valueName, $message = null)
    {
        return "Expected type {$typeName}, found {$valueName}"  .
            ($message ? "; ${message}" : '.');
    }

    static function badArgumentValueMessage($typeName, $valueName, $fieldName, $argName, $message = null)
    {
        return "Field \"{$fieldName}\" argument \"{$argName}\" requires type {$typeName}, found {$valueName}" .
            ($message ? "; {$message}" : '.');
    }

    static function requiredFieldMessage($typeName, $fieldName, $fieldTypeName)
    {
        return "Field {$typeName}.{$fieldName} of required type " .
            "{$fieldTypeName} was not provided.";
    }

    static function unknownFieldMessage($typeName, $fieldName, $message = null)
    {
        return (
            "Field \"{$fieldName}\" is not defined by type {$typeName}" .
            ($message ? "; {$message}" : '.')
        );
    }

    private static function getBadValueMessage($typeName, $valueName, $message = null, $context = null, $fieldName = null)
    {
        if ($context AND $arg = $context->getArgument()) {
            return self::badArgumentValueMessage($typeName, $valueName, $fieldName, $arg->name, $message);
        } else {
            return self::badValueMessage($typeName, $valueName, $message);
        }
    }

    public function getVisitor(ValidationContext $context)
    {
        $fieldName = '';
        return [
            NodeKind::FIELD => [
                'enter' => function (FieldNode $node) use (&$fieldName) {
                    $fieldName = $node->name->value;
                }
            ],
            NodeKind::NULL => function(NullValueNode $node) use ($context, &$fieldName) {
                $type = $context->getInputType();
                if ($type instanceof NonNull) {
                    $context->reportError(
                      new Error(
                          self::getBadValueMessage((string) $type, Printer::doPrint($node), null, $context, $fieldName),
                          $node
                      )
                    );
                }
            },
            NodeKind::LST => function(ListValueNode $node) use ($context, &$fieldName) {
                // Note: TypeInfo will traverse into a list's item type, so look to the
                // parent input type to check if it is a list.
                $type = Type::getNullableType($context->getParentInputType());
                if (!$type instanceof ListOfType) {
                    $this->isValidScalar($context, $node, $fieldName);
                    return Visitor::skipNode();
                }
            },
            NodeKind::OBJECT => function(ObjectValueNode $node) use ($context, &$fieldName) {
                // Note: TypeInfo will traverse into a list's item type, so look to the
                // parent input type to check if it is a list.
                $type = Type::getNamedType($context->getInputType());
                if (!$type instanceof InputObjectType) {
                    $this->isValidScalar($context, $node, $fieldName);
                    return Visitor::skipNode();
                }
                unset($fieldName);
                // Ensure every required field exists.
                $inputFields = $type->getFields();
                $nodeFields = iterator_to_array($node->fields);
                $fieldNodeMap = array_combine(
                    array_map(function ($field) { return $field->name->value; }, $nodeFields),
                    array_values($nodeFields)
                );
                foreach ($inputFields as $fieldName => $fieldDef) {
                    $fieldType = $fieldDef->getType();
                    if (!isset($fieldNodeMap[$fieldName]) && $fieldType instanceof NonNull) {
                        $context->reportError(
                            new Error(
                                self::requiredFieldMessage($type->name, $fieldName, (string) $fieldType),
                                $node
                            )
                        );
                    }
                }
            },
            NodeKind::OBJECT_FIELD => function(ObjectFieldNode $node) use ($context) {
                $parentType = Type::getNamedType($context->getParentInputType());
                $fieldType = $context->getInputType();
                if (!$fieldType && $parentType instanceof InputObjectType) {
                    $suggestions = Utils::suggestionList(
                        $node->name->value,
                        array_keys($parentType->getFields())
                    );
                    $didYouMean = $suggestions
                        ? "Did you mean " . Utils::orList($suggestions) . "?"
                        : null;

                    $context->reportError(
                        new Error(
                            self::unknownFieldMessage($parentType->name, $node->name->value, $didYouMean),
                            $node
                        )
                    );
                }
            },
            NodeKind::ENUM => function(EnumValueNode $node) use ($context, &$fieldName) {
                $type = Type::getNamedType($context->getInputType());
                if (!$type instanceof EnumType) {
                    $this->isValidScalar($context, $node, $fieldName);
                } else if (!$type->getValue($node->value)) {
                    $context->reportError(
                        new Error(
                            self::getBadValueMessage(
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
            NodeKind::INT => function (IntValueNode $node) use ($context, &$fieldName) { $this->isValidScalar($context, $node, $fieldName); },
            NodeKind::FLOAT => function (FloatValueNode $node) use ($context, &$fieldName) { $this->isValidScalar($context, $node, $fieldName); },
            NodeKind::STRING => function (StringValueNode $node) use ($context, &$fieldName) { $this->isValidScalar($context, $node, $fieldName); },
            NodeKind::BOOLEAN => function (BooleanValueNode $node) use ($context, &$fieldName) { $this->isValidScalar($context, $node, $fieldName); },
        ];
    }

    private function isValidScalar(ValidationContext $context, ValueNode $node, $fieldName)
    {
        // Report any error at the full type expected by the location.
        $locationType = $context->getInputType();

        if (!$locationType) {
            return;
        }

        $type = Type::getNamedType($locationType);

        if (!$type instanceof ScalarType) {
            $context->reportError(
                new Error(
                    self::getBadValueMessage(
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
        } catch (\Exception $error) {
            // We should not pass $error to "previous" parameter of Error's constructor here,
            // otherwise this error will be in "internal" category instead of "graphql".
            $context->reportError(
                new Error(
                    self::getBadValueMessage(
                        (string) $locationType,
                        Printer::doPrint($node),
                        $error->getMessage(),
                        $context,
                        $fieldName
                    ),
                    $node
                )
            );
        } catch (\Throwable $error) {
            // We should not pass $error to "previous" parameter of Error's constructor here,
            // otherwise this error will be in "internal" category instead of "graphql".
            $context->reportError(
                new Error(
                    self::getBadValueMessage(
                        (string) $locationType,
                        Printer::doPrint($node),
                        $error->getMessage(),
                        $context,
                        $fieldName
                    ),
                    $node
                )
            );
        }
    }

    private function enumTypeSuggestion($type, ValueNode $node)
    {
        if ($type instanceof EnumType) {
            $suggestions = Utils::suggestionList(
                Printer::doPrint($node),
                array_map(function (EnumValueDefinition $value) {
                    return $value->name;
                }, $type->getValues())
            );

            return $suggestions ? 'Did you mean the enum value ' . Utils::orList($suggestions) . '?' : null;
        }
    }
}
