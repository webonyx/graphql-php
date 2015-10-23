<?php
namespace GraphQL\Executor;


use GraphQL\Error;
use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\ListType;
use GraphQL\Language\AST\ListValue;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectValue;
use GraphQL\Language\AST\Value;
use GraphQL\Language\AST\Variable;
use GraphQL\Language\AST\VariableDefinition;
use GraphQL\Language\Printer;
use GraphQL\Schema;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils;

class Values
{
    /**
     * Prepares an object map of variables of the correct type based on the provided
     * variable definitions and arbitrary input. If the input cannot be coerced
     * to match the variable definitions, a Error will be thrown.
     *
     * @param Schema $schema
     * @param VariableDefinition[] $definitionASTs
     * @param array $inputs
     * @return array
     * @throws Error
     */
    public static function getVariableValues(Schema $schema, $definitionASTs, array $inputs)
    {
        $values = [];
        foreach ($definitionASTs as $defAST) {
            $varName = $defAST->variable->name->value;
            $values[$varName] = self::getvariableValue($schema, $defAST, isset($inputs[$varName]) ? $inputs[$varName] : null);
        }
        return $values;
    }

    /**
     * Prepares an object map of argument values given a list of argument
     * definitions and list of argument AST nodes.
     *
     * @param FieldArgument[] $argDefs
     * @param Argument[] $argASTs
     * @param $variableValues
     * @return array
     */
    public static function getArgumentValues($argDefs, $argASTs, $variableValues)
    {
        if (!$argDefs) {
            return [];
        }
        $argASTMap = $argASTs ? Utils::keyMap($argASTs, function ($arg) {
            return $arg->name->value;
        }) : [];
        $result = [];
        foreach ($argDefs as $argDef) {
            $name = $argDef->name;
            $valueAST = isset($argASTMap[$name]) ? $argASTMap[$name]->value : null;
            $value = self::valueFromAST($valueAST, $argDef->getType(), $variableValues);

            if (null === $value) {
                $value = $argDef->defaultValue;
            }
            if (null !== $value) {
                $result[$name] = $value;
            }
        }
        return $result;
    }

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
                    return Values::valueFromAST($itemAST, $itemType, $variables);
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

    /**
     * Given a variable definition, and any value of input, return a value which
     * adheres to the variable definition, or throw an error.
     */
    private static function getVariableValue(Schema $schema, VariableDefinition $definitionAST, $input)
    {
        $type = Utils\TypeInfo::typeFromAST($schema, $definitionAST->type);
        $variable = $definitionAST->variable;

        if (!$type || !Type::isInputType($type)) {
            $printed = Printer::doPrint($definitionAST->type);
            throw new Error(
                "Variable \"\${$variable->name->value}\" expected value of type " .
                "\"$printed\" which cannot be used as an input type.",
                [ $definitionAST ]
            );
        }
        if (self::isValidValue($input, $type)) {
            if (null === $input) {
                $defaultValue = $definitionAST->defaultValue;
                if ($defaultValue) {
                    return self::valueFromAST($defaultValue, $type);
                }
            }
            return self::coerceValue($type, $input);
        }

        throw new Error(
            "Variable \${$definitionAST->variable->name->value} expected value of type " .
            Printer::doPrint($definitionAST->type) . " but got: " . json_encode($input) . '.',
            [$definitionAST]
        );
    }


    /**
     * Given a PHP value and a GraphQL type, determine if the value will be
     * accepted for that type. This is primarily useful for validating the
     * runtime values of query variables.
     *
     * @param $value
     * @param Type $type
     * @return bool
     */
    private static function isValidValue($value, Type $type)
    {
        if ($type instanceof NonNull) {
            if (null === $value) {
                return false;
            }
            return self::isValidValue($value, $type->getWrappedType());
        }

        if ($value === null) {
            return true;
        }

        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            if (is_array($value)) {
                foreach ($value as $item) {
                    if (!self::isValidValue($item, $itemType)) {
                        return false;
                    }
                }
                return true;
            } else {
                return self::isValidValue($value, $itemType);
            }
        }

        if ($type instanceof InputObjectType) {
            if (!is_array($value)) {
                return false;
            }
            $fields = $type->getFields();
            $fieldMap = [];

            // Ensure every defined field is valid.
            foreach ($fields as $fieldName => $field) {
                /** @var FieldDefinition $field */
                if (!self::isValidValue(isset($value[$fieldName]) ? $value[$fieldName] : null, $field->getType())) {
                    return false;
                }
                $fieldMap[$field->name] = $field;
            }

            // Ensure every provided field is defined.
            $diff = array_diff_key($value, $fieldMap);

            if (!empty($diff)) {
                return false;
            }

            return true;
        }

        Utils::invariant($type instanceof ScalarType || $type instanceof EnumType, 'Must be input type');
        return null !== $type->parseValue($value);
    }

    /**
     * Given a type and any value, return a runtime value coerced to match the type.
     */
    private static function coerceValue(Type $type, $value)
    {
        if ($type instanceof NonNull) {
            // Note: we're not checking that the result of coerceValue is non-null.
            // We only call this function after calling isValidValue.
            return self::coerceValue($type->getWrappedType(), $value);
        }

        if (null === $value) {
            return null;
        }

        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            // TODO: support iterable input
            if (is_array($value)) {
                return array_map(function ($item) use ($itemType) {
                    return Values::coerceValue($itemType, $item);
                }, $value);
            } else {
                return [self::coerceValue($itemType, $value)];
            }
        }

        if ($type instanceof InputObjectType) {
            $fields = $type->getFields();
            $obj = [];
            foreach ($fields as $fieldName => $field) {
                $fieldValue = self::coerceValue($field->getType(), isset($value[$fieldName]) ? $value[$fieldName] : null);
                if (null === $fieldValue) {
                    $fieldValue = $field->defaultValue;
                }
                if (null !== $fieldValue) {
                    $obj[$fieldName] = $fieldValue;
                }
            }
            return $obj;

        }
        Utils::invariant($type instanceof ScalarType || $type instanceof EnumType, 'Must be input type');
        return $type->parseValue($value);
    }
}
