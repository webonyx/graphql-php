<?php declare(strict_types=1);

namespace GraphQL\Utils;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;

use GraphQL\Error\Error;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;

use function is_array;
use function is_string;

use stdClass;
use Throwable;
use Traversable;

/**
 * Coerces a PHP value given a GraphQL Type.
 *
 * Returns either a value which is valid for the provided type or a list of
 * encountered coercion errors.
 *
 * @phpstan-type CoercedValue array{errors: null, value: mixed}
 * @phpstan-type CoercedErrors array{errors: array<int, Error>, value: null}
 *
 * The key prev should actually also be typed as Path, but PHPStan does not support recursive types.
 * @phpstan-type Path array{prev: array<string, mixed>|null, key: string|int}
 */
class Value
{
    /**
     * Given a type and any value, return a runtime value coerced to match the type.
     *
     * @param mixed $value
     * @param InputType&Type $type
     * @phpstan-param Path|null $path
     *
     * @phpstan-return CoercedValue|CoercedErrors
     */
    public static function coerceValue($value, InputType $type, ?VariableDefinitionNode $blameNode = null, ?array $path = null): array
    {
        if ($type instanceof NonNull) {
            if ($value === null) {
                return self::ofErrors([
                    self::coercionError(
                        "Expected non-nullable type {$type} not to be null",
                        $blameNode,
                        $path
                    ),
                ]);
            }

            // @phpstan-ignore-next-line wrapped type is known to be input type after schema validation
            return self::coerceValue($value, $type->getWrappedType(), $blameNode, $path);
        }

        if ($value === null) {
            // Explicitly return the value null.
            return self::ofValue(null);
        }

        if ($type instanceof ScalarType) {
            // Scalars determine if a value is valid via parseValue(), which can
            // throw to indicate failure. If it throws, maintain a reference to
            // the original error.
            try {
                return self::ofValue($type->parseValue($value));
            } catch (Throwable $error) {
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
            try {
                return self::ofValue($type->parseValue($value));
            } catch (Throwable $error) {
                $suggestions = Utils::suggestionList(
                    Utils::printSafe($value),
                    array_map(
                        static fn (EnumValueDefinition $enumValue): string => $enumValue->name,
                        $type->getValues()
                    )
                );

                $didYouMean = $suggestions === []
                    ? null
                    : 'did you mean ' . Utils::orList($suggestions) . '?';

                return self::ofErrors([
                    self::coercionError(
                        "Expected type {$type->name}",
                        $blameNode,
                        $path,
                        $didYouMean,
                        $error
                    ),
                ]);
            }
        }

        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            assert($itemType instanceof InputType, 'known through schema validation');

            if (is_array($value) || $value instanceof Traversable) {
                $errors = [];
                $coercedValue = [];
                foreach ($value as $index => $itemValue) {
                    $coercedItem = self::coerceValue(
                        $itemValue,
                        $itemType,
                        $blameNode,
                        self::atPath($path, $index)
                    );

                    if (isset($coercedItem['errors'])) {
                        $errors = self::add($errors, $coercedItem['errors']);
                    } else {
                        $coercedValue[] = $coercedItem['value'];
                    }
                }

                return $errors === []
                    ? self::ofValue($coercedValue)
                    : self::ofErrors($errors);
            }

            // Lists accept a non-list value as a list of one.
            $coercedItem = self::coerceValue($value, $itemType, $blameNode);

            return isset($coercedItem['errors'])
                ? $coercedItem
                : self::ofValue([$coercedItem['value']]);
        }

        assert($type instanceof InputObjectType, 'we handled all other cases at this point');

        if ($value instanceof stdClass) {
            // Cast objects to associative array before checking the fields.
            // Note that the coerced value will be an array.
            $value = (array) $value;
        } elseif (! is_array($value)) {
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
            if (array_key_exists($fieldName, $value)) {
                $fieldValue = $value[$fieldName];
                $coercedField = self::coerceValue(
                    $fieldValue,
                    $field->getType(),
                    $blameNode,
                    self::atPath($path, $fieldName)
                );

                if (isset($coercedField['errors'])) {
                    $errors = self::add($errors, $coercedField['errors']);
                } else {
                    $coercedValue[$fieldName] = $coercedField['value'];
                }
            } elseif ($field->defaultValueExists()) {
                $coercedValue[$fieldName] = $field->defaultValue;
            } elseif ($field->getType() instanceof NonNull) {
                $fieldPath = self::printPath(self::atPath($path, $fieldName));
                $errors = self::add(
                    $errors,
                    self::coercionError(
                        "Field {$fieldPath} of required type {$field->getType()->toString()} was not provided",
                        $blameNode
                    )
                );
            }
        }

        // Ensure every provided field is defined.
        foreach ($value as $fieldName => $field) {
            if (array_key_exists($fieldName, $fields)) {
                continue;
            }

            $suggestions = Utils::suggestionList(
                (string) $fieldName,
                array_keys($fields)
            );
            $didYouMean = $suggestions === []
                ? null
                : 'did you mean ' . Utils::orList($suggestions) . '?';
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

        return $errors === []
            ? self::ofValue($type->parseValue($coercedValue))
            : self::ofErrors($errors);
    }

    /**
     * @param array<int, Error> $errors
     *
     * @phpstan-return CoercedErrors
     */
    private static function ofErrors(array $errors): array
    {
        return ['errors' => $errors, 'value' => null];
    }

    /**
     * @phpstan-param Path|null $path
     */
    private static function coercionError(
        string $message,
        ?VariableDefinitionNode $blameNode,
        ?array $path = null,
        ?string $subMessage = null,
        ?Throwable $originalError = null
    ): Error {
        $pathStr = self::printPath($path);

        $fullMessage = $message
            . ($pathStr === ''
                ? ''
                : ' at ' . $pathStr)
            . ($subMessage === null || $subMessage === ''
                ? '.'
                : '; ' . $subMessage);

        return new Error(
            $fullMessage,
            $blameNode,
            null,
            [],
            null,
            $originalError
        );
    }

    /**
     * Build a string describing the path into the value where the error was found.
     *
     * @phpstan-param Path|null $path
     */
    private static function printPath(?array $path = null): string
    {
        if ($path === null) {
            return '';
        }

        $pathStr = '';
        do {
            $key = $path['key'];
            $pathStr = (is_string($key)
                    ? ".{$key}"
                    : "[{$key}]")
                . $pathStr;
            $path = $path['prev'];
        } while ($path !== null);

        return "value{$pathStr}";
    }

    /**
     * @param mixed $value any value
     *
     * @phpstan-return CoercedValue
     */
    private static function ofValue($value): array
    {
        return ['errors' => null, 'value' => $value];
    }

    /**
     * @param string|int $key
     * @phpstan-param Path|null $prev
     *
     * @return Path
     */
    private static function atPath(?array $prev, $key): array
    {
        return ['prev' => $prev, 'key' => $key];
    }

    /**
     * @param array<int, Error>       $errors
     * @param Error|array<int, Error> $errorOrErrors
     *
     * @return array<int, Error>
     */
    private static function add(array $errors, $errorOrErrors): array
    {
        $moreErrors = is_array($errorOrErrors)
            ? $errorOrErrors
            : [$errorOrErrors];

        return array_merge($errors, $moreErrors);
    }
}
