<?php
namespace GraphQL\Utils;

use GraphQL\Language\AST\BooleanValue;
use GraphQL\Language\AST\EnumValue;
use GraphQL\Language\AST\FloatValue;
use GraphQL\Language\AST\IntValue;
use GraphQL\Language\AST\ListType;
use GraphQL\Language\AST\ListValue;
use GraphQL\Language\AST\Name;
use GraphQL\Language\AST\ObjectField;
use GraphQL\Language\AST\ObjectValue;
use GraphQL\Language\AST\StringValue;
use GraphQL\Language\AST\Variable;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FloatType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils;

/**
 * Class AST
 * @package GraphQL\Utils
 */
class AST
{
    /**
     * Produces a GraphQL Value AST given a PHP value.
     *
     * Optionally, a GraphQL type may be provided, which will be used to
     * disambiguate between value primitives.
     *
     * | JSON Value    | GraphQL Value        |
     * | ------------- | -------------------- |
     * | Object        | Input Object         |
     * | Assoc Array   | Input Object         |
     * | Array         | List                 |
     * | Boolean       | Boolean              |
     * | String        | String / Enum Value  |
     * | Int           | Int                  |
     * | Float         | Float                |
     */
    static function astFromValue($value, Type $type = null)
    {
        if ($type instanceof NonNull) {
            // Note: we're not checking that the result is non-null.
            // This function is not responsible for validating the input value.
            return self::astFromValue($value, $type->getWrappedType());
        }

        if ($value === null) {
            return null;
        }

        // Check if $value is associative array, assuming that associative array always has first key as string
        // (can make such assumption because GraphQL field names can never be integers, so mixed arrays are not valid anyway)
        $isAssoc = false;
        if (is_array($value)) {
            if (!empty($value)) {
                reset($value);
                $isAssoc = is_string(key($value));
            } else {
                $isAssoc = ($type instanceof InputObjectType) || !($type instanceof ListOfType);
            }
        }

        // Convert PHP array to GraphQL list. If the GraphQLType is a list, but
        // the value is not an array, convert the value using the list's item type.
        if (is_array($value) && !$isAssoc) {
            $itemType = $type instanceof ListOfType ? $type->getWrappedType() : null;
            return new ListValue([
                'values' => Utils::map($value, function ($item) use ($itemType) {
                    $itemValue = self::astFromValue($item, $itemType);
                    Utils::invariant($itemValue, 'Could not create AST item.');
                    return $itemValue;
                })
            ]);
        } else if ($type instanceof ListOfType) {
            // Because GraphQL will accept single values as a "list of one" when
            // expecting a list, if there's a non-array value and an expected list type,
            // create an AST using the list's item type.
            return self::astFromValue($value, $type->getWrappedType());
        }

        if (is_bool($value)) {
            return new BooleanValue(['value' => $value]);
        }

        if (is_int($value)) {
            if ($type instanceof FloatType) {
                return new FloatValue(['value' => (string)(float)$value]);
            }
            return new IntValue(['value' => $value]);
        } else if (is_float($value)) {
            $tmp = (int) $value;
            if ($tmp == $value && (!$type instanceof FloatType)) {
                return new IntValue(['value' => (string)$tmp]);
            } else {
                return new FloatValue(['value' => (string)$value]);
            }
        }

        // PHP strings can be Enum values or String values. Use the
        // GraphQLType to differentiate if possible.
        if (is_string($value)) {
            if ($type instanceof EnumType && preg_match('/^[_a-zA-Z][_a-zA-Z0-9]*$/', $value)) {
                return new EnumValue(['value' => $value]);
            }

            // Use json_encode, which uses the same string encoding as GraphQL,
            // then remove the quotes.
            return new StringValue([
                'value' => mb_substr(json_encode($value), 1, -1)
            ]);
        }

        // last remaining possible type
        Utils::invariant(is_object($value) || $isAssoc);

        // Populate the fields of the input object by creating ASTs from each value
        // in the PHP object.
        $fields = [];
        $tmp = $isAssoc ? $value : get_object_vars($value);
        foreach ($tmp as $fieldName => $objValue) {
            $fieldType = null;
            if ($type instanceof InputObjectType) {
                $tmp = $type->getFields();
                $fieldDef = isset($tmp[$fieldName]) ? $tmp[$fieldName] : null;
                $fieldType = $fieldDef ? $fieldDef->getType() : null;
            }

            $fieldValue = self::astFromValue($objValue, $fieldType);

            if ($fieldValue) {
                $fields[] = new ObjectField([
                    'name' => new Name(['value' => $fieldName]),
                    'value' => $fieldValue
                ]);
            }
        }
        return new ObjectValue([
            'fields' => $fields
        ]);
    }

    /**
     * @param $valueAST
     * @param InputType $type
     * @param null $variables
     * @return array|null
     * @throws \Exception
     */
    public static function valueFromAST($valueAST, InputType $type, $variables = null)
    {
        if ($type instanceof NonNull) {
            return self::valueFromAST($valueAST, $type->getWrappedType(), $variables);
        }

        if (!$valueAST) {
            return null;
        }

        if ($valueAST instanceof Variable) {
            $variableName = $valueAST->name->value;

            if (!$variables || !isset($variables[$variableName])) {
                return null;
            }
            return $variables[$variableName];
        }

        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            if ($valueAST instanceof ListValue) {
                return array_map(function($itemAST) use ($itemType, $variables) {
                    return self::valueFromAST($itemAST, $itemType, $variables);
                }, $valueAST->values);
            } else {
                return [self::valueFromAST($valueAST, $itemType, $variables)];
            }
        }

        if ($type instanceof InputObjectType) {
            $fields = $type->getFields();
            if (!$valueAST instanceof ObjectValue) {
                return null;
            }
            $fieldASTs = Utils::keyMap($valueAST->fields, function($field) {return $field->name->value;});
            $values = [];
            foreach ($fields as $field) {
                $fieldAST = isset($fieldASTs[$field->name]) ? $fieldASTs[$field->name] : null;
                $fieldValue = self::valueFromAST($fieldAST ? $fieldAST->value : null, $field->getType(), $variables);

                if (null === $fieldValue) {
                    $fieldValue = $field->defaultValue;
                }
                if (null !== $fieldValue) {
                    $values[$field->name] = $fieldValue;
                }
            }
            return $values;
        }

        Utils::invariant($type instanceof ScalarType || $type instanceof EnumType, 'Must be input type');
        return $type->parseLiteral($valueAST);
    }
}
