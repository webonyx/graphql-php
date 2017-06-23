<?php
namespace GraphQL\Utils;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\ValueNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\IDType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\LeafType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
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
     * | PHP Value     | GraphQL Value        |
     * | ------------- | -------------------- |
     * | Object        | Input Object         |
     * | Assoc Array   | Input Object         |
     * | Array         | List                 |
     * | Boolean       | Boolean              |
     * | String        | String / Enum Value  |
     * | Int           | Int                  |
     * | Float         | Int / Float          |
     * | Mixed         | Enum Value           |
     * | null          | NullValue            |
     *
     * @param $value
     * @param InputType $type
     * @return ObjectValueNode|ListValueNode|BooleanValueNode|IntValueNode|FloatValueNode|EnumValueNode|StringValueNode|NullValueNode
     */
    public static function astFromValue($value, InputType $type)
    {
        if ($type instanceof NonNull) {
            $astValue = self::astFromValue($value, $type->getWrappedType());
            if ($astValue instanceof NullValueNode) {
                return null;
            }
            return $astValue;
        }

        if ($value === null) {
            return new NullValueNode([]);
        }

        // Convert PHP array to GraphQL list. If the GraphQLType is a list, but
        // the value is not an array, convert the value using the list's item type.
        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            if (is_array($value) || ($value instanceof \Traversable)) {
                $valuesNodes = [];
                foreach ($value as $item) {
                    $itemNode = self::astFromValue($item, $itemType);
                    if ($itemNode) {
                        $valuesNodes[] = $itemNode;
                    }
                }
                return new ListValueNode(['values' => $valuesNodes]);
            }
            return self::astFromValue($value, $itemType);
        }

        // Populate the fields of the input object by creating ASTs from each value
        // in the PHP object according to the fields in the input type.
        if ($type instanceof InputObjectType) {
            $isArray = is_array($value);
            $isArrayLike = $isArray || $value instanceof \ArrayAccess;
            if ($value === null || (!$isArrayLike && !is_object($value))) {
                return null;
            }
            $fields = $type->getFields();
            $fieldNodes = [];
            foreach ($fields as $fieldName => $field) {
                if ($isArrayLike) {
                    $fieldValue = isset($value[$fieldName]) ? $value[$fieldName] : null;
                } else {
                    $fieldValue = isset($value->{$fieldName}) ? $value->{$fieldName} : null;
                }

                // Have to check additionally if key exists, since we differentiate between
                // "no key" and "value is null":
                if (null !== $fieldValue) {
                    $fieldExists = true;
                } elseif ($isArray) {
                    $fieldExists = array_key_exists($fieldName, $value);
                } elseif ($isArrayLike) {
                    /** @var \ArrayAccess $value */
                    $fieldExists = $value->offsetExists($fieldName);
                } else {
                    $fieldExists = property_exists($value, $fieldName);
                }

                if ($fieldExists) {
                    $fieldNode = self::astFromValue($fieldValue, $field->getType());

                    if ($fieldNode) {
                        $fieldNodes[] = new ObjectFieldNode([
                            'name' => new NameNode(['value' => $fieldName]),
                            'value' => $fieldNode
                        ]);
                    }
                }
            }
            return new ObjectValueNode(['fields' => $fieldNodes]);
        }

        // Since value is an internally represented value, it must be serialized
        // to an externally represented value before converting into an AST.
        if ($type instanceof LeafType) {
            $serialized = $type->serialize($value);
        } else {
            throw new InvariantViolation("Must provide Input Type, cannot use: " . Utils::printSafe($type));
        }

        if (null === $serialized) {
            return null;
        }

        // Others serialize based on their corresponding PHP scalar types.
        if (is_bool($serialized)) {
            return new BooleanValueNode(['value' => $serialized]);
        }
        if (is_int($serialized)) {
            return new IntValueNode(['value' => $serialized]);
        }
        if (is_float($serialized)) {
            if ((int) $serialized == $serialized) {
                return new IntValueNode(['value' => $serialized]);
            }
            return new FloatValueNode(['value' => $serialized]);
        }
        if (is_string($serialized)) {
            // Enum types use Enum literals.
            if ($type instanceof EnumType) {
                return new EnumValueNode(['value' => $serialized]);
            }

            // ID types can use Int literals.
            $asInt = (int) $serialized;
            if ($type instanceof IDType && (string) $asInt === $serialized) {
                return new IntValueNode(['value' => $serialized]);
            }

            // Use json_encode, which uses the same string encoding as GraphQL,
            // then remove the quotes.
            return new StringValueNode([
                'value' => substr(json_encode($serialized), 1, -1)
            ]);
        }

        throw new InvariantViolation('Cannot convert value to AST: ' . Utils::printSafe($serialized));
    }

    /**
     * Produces a PHP value given a GraphQL Value AST.
     *
     * A GraphQL type must be provided, which will be used to interpret different
     * GraphQL Value literals.
     *
     * Returns `null` when the value could not be validly coerced according to
     * the provided type.
     *
     * | GraphQL Value        | PHP Value     |
     * | -------------------- | ------------- |
     * | Input Object         | Assoc Array   |
     * | List                 | Array         |
     * | Boolean              | Boolean       |
     * | String               | String        |
     * | Int / Float          | Int / Float   |
     * | Enum Value           | Mixed         |
     * | Null Value           | stdClass      | instance of NullValue::getNullValue()
     *
     * @param $valueNode
     * @param InputType $type
     * @param null $variables
     * @return array|null|\stdClass
     * @throws \Exception
     */
    public static function valueFromAST($valueNode, InputType $type, $variables = null)
    {
        $undefined = Utils::undefined();

        if (!$valueNode) {
            // When there is no AST, then there is also no value.
            // Importantly, this is different from returning the GraphQL null value.
            return $undefined;
        }

        if ($type instanceof NonNull) {
            if ($valueNode instanceof NullValueNode) {
                // Invalid: intentionally return no value.
                return $undefined;
            }
            return self::valueFromAST($valueNode, $type->getWrappedType(), $variables);
        }

        if ($valueNode instanceof NullValueNode) {
            // This is explicitly returning the value null.
            return null;
        }

        if ($valueNode instanceof VariableNode) {
            $variableName = $valueNode->name->value;

            if (!$variables || !array_key_exists($variableName, $variables)) {
                // No valid return value.
                return $undefined;
            }
            // Note: we're not doing any checking that this variable is correct. We're
            // assuming that this query has been validated and the variable usage here
            // is of the correct type.
            return $variables[$variableName];
        }

        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();

            if ($valueNode instanceof ListValueNode) {
                $coercedValues = [];
                $itemNodes = $valueNode->values;
                foreach ($itemNodes as $itemNode) {
                    if (self::isMissingVariable($itemNode, $variables)) {
                        // If an array contains a missing variable, it is either coerced to
                        // null or if the item type is non-null, it considered invalid.
                        if ($itemType instanceof NonNull) {
                            // Invalid: intentionally return no value.
                            return $undefined;
                        }
                        $coercedValues[] = null;
                    } else {
                        $itemValue = self::valueFromAST($itemNode, $itemType, $variables);
                        if ($undefined === $itemValue) {
                            // Invalid: intentionally return no value.
                            return $undefined;
                        }
                        $coercedValues[] = $itemValue;
                    }
                }
                return $coercedValues;
            }
            $coercedValue = self::valueFromAST($valueNode, $itemType, $variables);
            if ($undefined === $coercedValue) {
                // Invalid: intentionally return no value.
                return $undefined;
            }
            return [$coercedValue];
        }

        if ($type instanceof InputObjectType) {
            if (!$valueNode instanceof ObjectValueNode) {
                // Invalid: intentionally return no value.
                return $undefined;
            }

            $coercedObj = [];
            $fields = $type->getFields();
            $fieldNodes = Utils::keyMap($valueNode->fields, function ($field) {
                return $field->name->value;
            });
            foreach ($fields as $field) {
                /** @var ValueNode $fieldNode */
                $fieldName = $field->name;
                $fieldNode = isset($fieldNodes[$fieldName]) ? $fieldNodes[$fieldName] : null;

                if (!$fieldNode || self::isMissingVariable($fieldNode->value, $variables)) {
                    if ($field->defaultValueExists()) {
                        $coercedObj[$fieldName] = $field->defaultValue;
                    } elseif ($field->getType() instanceof NonNull) {
                        // Invalid: intentionally return no value.
                        return $undefined;
                    }
                    continue ;
                }

                $fieldValue = self::valueFromAST($fieldNode ? $fieldNode->value : null, $field->getType(), $variables);

                if ($undefined === $fieldValue) {
                    // Invalid: intentionally return no value.
                    return $undefined;
                }
                $coercedObj[$fieldName] = $fieldValue;
            }
            return $coercedObj;
        }

        if ($type instanceof LeafType) {
            $parsed = $type->parseLiteral($valueNode);

            if (null === $parsed) {
                // null represent a failure to parse correctly,
                // in which case no value is returned.
                return $undefined;
            }

            return $parsed;
        }

        throw new InvariantViolation('Must be input type');
    }


    /**
     * Returns true if the provided valueNode is a variable which is not defined
     * in the set of variables.
     * @param $valueNode
     * @param $variables
     * @return bool
     */
    private static function isMissingVariable($valueNode, $variables)
    {
        return $valueNode instanceof VariableNode &&
        (!$variables || !array_key_exists($valueNode->name->value, $variables));
    }
}
