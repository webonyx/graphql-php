<?php
namespace GraphQL\Executor;


use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\VariableDefinition;
use GraphQL\Language\Printer;
use GraphQL\Schema;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\LeafType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
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
            $varName = $defAST->getVariable()->getName()->getValue();
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
            return $arg->getName()->getValue();
        }) : [];
        $result = [];
        foreach ($argDefs as $argDef) {
            $name = $argDef->name;
            $valueAST = isset($argASTMap[$name]) ? $argASTMap[$name]->getValue() : null;
            $value = Utils\AST::valueFromAST($valueAST, $argDef->getType(), $variableValues);

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
        return Utils\AST::valueFromAST($valueAST, $type, $variables);
    }

    /**
     * Given a variable definition, and any value of input, return a value which
     * adheres to the variable definition, or throw an error.
     */
    private static function getVariableValue(Schema $schema, VariableDefinition $definitionAST, $input)
    {
        $type = Utils\TypeInfo::typeFromAST($schema, $definitionAST->getType());
        $variable = $definitionAST->getVariable();

        if (!$type || !Type::isInputType($type)) {
            $printer = new Printer();
            $printed = $printer->doPrint($definitionAST->getType());
            throw new Error(
                "Variable \"\${$variable->getName()->getValue()}\" expected value of type " .
                "\"$printed\" which cannot be used as an input type.",
                [ $definitionAST ]
            );
        }

        $inputType = $type;
        $errors = self::isValidPHPValue($input, $inputType);

        if (empty($errors)) {
            if (null === $input) {
                $defaultValue = $definitionAST->getDefaultValue();
                if ($defaultValue) {
                    return Utils\AST::valueFromAST($defaultValue, $inputType);
                }
            }
            return self::coerceValue($inputType, $input);
        }

        if (null === $input) {
            $printer = new Printer();
            $printed = $printer->doPrint($definitionAST->getType());

            throw new Error(
                "Variable \"\${$variable->getName()->getValue()}\" of required type " .
                "\"$printed\" was not provided.",
                [ $definitionAST ]
            );
        }
        $message = $errors ? "\n" . implode("\n", $errors) : '';
        $val = json_encode($input);
        throw new Error(
            "Variable \"\${$variable->getName()->getValue()}\" got invalid value ".
            "{$val}.{$message}",
            [ $definitionAST ]
        );
    }

    /**
     * Given a PHP value and a GraphQL type, determine if the value will be
     * accepted for that type. This is primarily useful for validating the
     * runtime values of query variables.
     *
     * @param $value
     * @param InputType $type
     * @return array
     */
    private static function isValidPHPValue($value, InputType $type)
    {
        // A value must be provided if the type is non-null.
        if ($type instanceof NonNull) {
            $ofType = $type->getWrappedType();
            if (null === $value) {
                if ($ofType->name) {
                    return [ "Expected \"{$ofType->name}!\", found null." ];
                }
                return [ 'Expected non-null value, found null.' ];
            }
            return self::isValidPHPValue($value, $ofType);
        }

        if (null === $value) {
            return [];
        }

        // Lists accept a non-list value as a list of one.
        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            if (is_array($value)) {
                $tmp = [];
                foreach ($value as $index => $item) {
                    $errors = self::isValidPHPValue($item, $itemType);
                    $tmp = array_merge($tmp, Utils::map($errors, function ($error) use ($index) {
                        return "In element #$index: $error";
                    }));
                }
                return $tmp;
            }
            return self::isValidPHPValue($value, $itemType);
        }

        // Input objects check each defined field.
        if ($type instanceof InputObjectType) {
            if (!is_object($value) && !is_array($value)) {
                return ["Expected \"{$type->name}\", found not an object."];
            }
            $fields = $type->getFields();
            $errors = [];

            // Ensure every provided field is defined.
            $props = is_object($value) ? get_object_vars($value) : $value;
            foreach ($props as $providedField => $tmp) {
                if (!isset($fields[$providedField])) {
                    $errors[] = "In field \"{$providedField}\": Unknown field.";
                }
            }

            // Ensure every defined field is valid.
            foreach ($fields as $fieldName => $tmp) {
                $newErrors = self::isValidPHPValue(isset($value[$fieldName]) ? $value[$fieldName] : null, $fields[$fieldName]->getType());
                $errors = array_merge(
                    $errors,
                    Utils::map($newErrors, function ($error) use ($fieldName) {
                        return "In field \"{$fieldName}\": {$error}";
                    })
                );
            }
            return $errors;
        }

        if ($type instanceof LeafType) {
            // Scalar/Enum input checks to ensure the type can parse the value to
            // a non-null value.
            $parseResult = $type->parseValue($value);
            if (null === $parseResult) {
                $v = json_encode($value);
                return [
                    "Expected type \"{$type->name}\", found $v."
                ];
            }
            return [];
        }

        throw new InvariantViolation('Must be input type');
    }

    /**
     * Given a type and any value, return a runtime value coerced to match the type.
     */
    private static function coerceValue(Type $type, $value)
    {
        if ($type instanceof NonNull) {
            // Note: we're not checking that the result of coerceValue is non-null.
            // We only call this function after calling isValidPHPValue.
            return self::coerceValue($type->getWrappedType(), $value);
        }

        if (null === $value) {
            return null;
        }

        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            if (is_array($value) || $value instanceof \Traversable) {
                return Utils::map($value, function($item) use ($itemType) {
                    return Values::coerceValue($itemType, $item);
                });
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

        if ($type instanceof LeafType) {
            return $type->parseValue($value);
        }

        throw new InvariantViolation('Must be input type');
    }
}
