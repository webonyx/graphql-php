<?php
namespace GraphQL\Utils;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ScalarType;

/**
 * Coerces a PHP value given a GraphQL Type.
 *
 * Returns either a value which is valid for the provided type or a list of
 * encountered coercion errors.
 */
class Value
{
    /**
     * Given a type and any value, return a runtime value coerced to match the type.
     */
    public static function coerceValue($value, InputType $type, $blameNode = null, array $path = null)
    {
        if ($type instanceof NonNull) {
            if ($value === null) {
                return self::ofErrors([
                    self::coercionError(
                        "Expected non-nullable type $type not to be null",
                        $blameNode,
                        $path
                    ),
                ]);
            }
            return self::coerceValue($value, $type->getWrappedType(), $blameNode, $path);
        }

        if (null === $value) {
            // Explicitly return the value null.
            return self::ofValue(null);
        }

        if ($type instanceof ScalarType) {
            // Scalars determine if a value is valid via parseValue(), which can
            // throw to indicate failure. If it throws, maintain a reference to
            // the original error.
            try {
                return self::ofValue($type->parseValue($value));
            } catch (\Exception $error) {
                return self::ofErrors([
                    self::coercionError(
                        "Expected type {$type->name}",
                        $blameNode,
                        $path,
                        $error->getMessage(),
                        $error
                    ),
                ]);
            } catch (\Throwable $error) {
                return self::ofErrors([
                    self::coercionError(
                        "Expected type {$type->name}",
                        $blameNode,
                        $path,
                        $error->getMessage(),
                        $error
                    ),
                ]);
            }
        }

        if ($type instanceof EnumType) {
            if (is_string($value)) {
                $enumValue = $type->getValue($value);
                if ($enumValue) {
                    return self::ofValue($enumValue->value);
                }
            }

            $suggestions = Utils::suggestionList(
                Utils::printSafe($value),
                array_map(function($enumValue) { return $enumValue->name; }, $type->getValues())
            );
            $didYouMean = $suggestions
                ? "did you mean " . Utils::orList($suggestions) . "?"
                : null;

            return self::ofErrors([
                self::coercionError(
                    "Expected type {$type->name}",
                    $blameNode,
                    $path,
                    $didYouMean
                ),
            ]);
        }

        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            if (is_array($value) || $value instanceof \Traversable) {
                $errors = [];
                $coercedValue = [];
                foreach ($value as $index => $itemValue) {
                    $coercedItem = self::coerceValue(
                        $itemValue,
                        $itemType,
                        $blameNode,
                        self::atPath($path, $index)
                    );
                    if ($coercedItem['errors']) {
                        $errors = self::add($errors, $coercedItem['errors']);
                    } else {
                        $coercedValue[] = $coercedItem['value'];
                    }
                }
                return $errors ? self::ofErrors($errors) : self::ofValue($coercedValue);
            }
            // Lists accept a non-list value as a list of one.
            $coercedItem = self::coerceValue($value, $itemType, $blameNode);
            return $coercedItem['errors'] ? $coercedItem : self::ofValue([$coercedItem['value']]);
        }

        if ($type instanceof InputObjectType) {
            if (!is_object($value) && !is_array($value) && !$value instanceof \Traversable) {
                return self::ofErrors([
                    self::coercionError(
                        "Expected type {$type->name} to be an object",
                        $blameNode,
                        $path
                    ),
                ]);
            }

            $errors = [];
            $coercedValue = [];
            $fields = $type->getFields();
            foreach ($fields as $fieldName => $field) {
                if (!array_key_exists($fieldName, $value)) {
                    if ($field->defaultValueExists()) {
                        $coercedValue[$fieldName] = $field->defaultValue;
                    } else if ($field->getType() instanceof NonNull) {
                        $fieldPath = self::printPath(self::atPath($path, $fieldName));
                        $errors = self::add(
                            $errors,
                            self::coercionError(
                                "Field {$fieldPath} of required " .
                                "type {$field->type} was not provided",
                                $blameNode
                            )
                        );
                    }
                } else {
                    $fieldValue = $value[$fieldName];
                    $coercedField = self::coerceValue(
                        $fieldValue,
                        $field->getType(),
                        $blameNode,
                        self::atPath($path, $fieldName)
                    );
                    if ($coercedField['errors']) {
                        $errors = self::add($errors, $coercedField['errors']);
                    } else {
                        $coercedValue[$fieldName] = $coercedField['value'];
                    }
                }
            }

            // Ensure every provided field is defined.
            foreach ($value as $fieldName => $field) {
                if (!array_key_exists($fieldName, $fields)) {
                    $suggestions = Utils::suggestionList(
                        $fieldName,
                        array_keys($fields)
                    );
                    $didYouMean = $suggestions
                        ? "did you mean " . Utils::orList($suggestions) . "?"
                        : null;
                    $errors = self::add(
                        $errors,
                        self::coercionError(
                            "Field \"{$fieldName}\" is not defined by type {$type->name}",
                            $blameNode,
                            $path,
                            $didYouMean
                        )
                    );
                }
            }

            return $errors ? self::ofErrors($errors) : self::ofValue($coercedValue);
        }

        throw new Error("Unexpected type {$type}");
    }

    private static function ofValue($value) {
        return ['errors' => null, 'value' => $value];
    }

    private static function ofErrors($errors) {
        return ['errors' => $errors, 'value' => Utils::undefined()];
    }

    private static function add($errors, $moreErrors) {
        return array_merge($errors, is_array($moreErrors) ? $moreErrors : [$moreErrors]);
    }

    private static function atPath($prev, $key) {
        return ['prev' => $prev, 'key' => $key];
    }

    /**
     * @param string $message
     * @param Node $blameNode
     * @param array|null $path
     * @param string $subMessage
     * @param \Exception|\Throwable|null $originalError
     * @return Error
     */
    private static function coercionError($message, $blameNode, array $path = null, $subMessage = null, $originalError = null) {
        $pathStr = self::printPath($path);
        // Return a GraphQLError instance
        return new Error(
            $message .
            ($pathStr ? ' at ' . $pathStr : '') .
            ($subMessage ? '; ' . $subMessage : '.'),
            $blameNode,
            null,
            null,
            null,
            $originalError
        );
    }

    /**
     * Build a string describing the path into the value where the error was found
     *
     * @param $path
     * @return string
     */
    private static function printPath(array $path = null) {
        $pathStr = '';
        $currentPath = $path;
        while($currentPath) {
            $pathStr =
                (is_string($currentPath['key'])
                    ? '.' . $currentPath['key']
                    : '[' . $currentPath['key'] . ']') . $pathStr;
            $currentPath = $currentPath['prev'];
        }
        return $pathStr ? 'value' . $pathStr : '';
    }
}
