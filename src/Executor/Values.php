<?php
namespace GraphQL\Executor;


use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\NullValue;
use GraphQL\Language\AST\Value;
use GraphQL\Language\AST\Variable;
use GraphQL\Language\AST\VariableDefinition;
use GraphQL\Language\Printer;
use GraphQL\Schema;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\LeafType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils;
use GraphQL\Validator\DocumentValidator;

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
        $coercedValues = [];
        foreach ($definitionASTs as $definitionAST) {
            $varName = $definitionAST->variable->name->value;
            $varType = Utils\TypeInfo::typeFromAST($schema, $definitionAST->type);

            if (!Type::isInputType($varType)) {
                throw new Error(
                    'Variable "$'.$varName.'" expected value of type ' .
                    '"' . Printer::doPrint($definitionAST->type) . '" which cannot be used as an input type.',
                    [$definitionAST->type]
                );
            }

            if (!array_key_exists($varName, $inputs)) {
                $defaultValue = $definitionAST->defaultValue;
                if ($defaultValue) {
                    $coercedValues[$varName] = Utils\AST::valueFromAST($defaultValue, $varType);
                }
                if ($varType instanceof NonNull) {
                    throw new Error(
                        'Variable "$'.$varName .'" of required type ' .
                        '"'. Utils::printSafe($varType) . '" was not provided.',
                        [$definitionAST]
                    );
                }
            } else {
                $value = $inputs[$varName];
                $errors = self::isValidPHPValue($value, $varType);
                if (!empty($errors)) {
                    $message = "\n" . implode("\n", $errors);
                    throw new Error(
                        'Variable "$' . $varName . '" got invalid value ' .
                        json_encode($value) . '.' . $message,
                        [$definitionAST]
                    );
                }

                $coercedValue = self::coerceValue($varType, $value);
                Utils::invariant($coercedValue !== Utils::undefined(), 'Should have reported error.');
                $coercedValues[$varName] = $coercedValue;
            }
        }
        return $coercedValues;
    }

    /**
     * Prepares an object map of argument values given a list of argument
     * definitions and list of argument AST nodes.
     *
     * @param FieldDefinition|Directive $def
     * @param Field|\GraphQL\Language\AST\Directive $node
     * @param $variableValues
     * @return array
     * @throws Error
     */
    public static function getArgumentValues($def, $node, $variableValues)
    {
        $argDefs = $def->args;
        $argASTs = $node->arguments;

        if (!$argDefs || null === $argASTs) {
            return [];
        }

        $coercedValues = [];
        $undefined = Utils::undefined();

        /** @var Argument[] $argASTMap */
        $argASTMap = $argASTs ? Utils::keyMap($argASTs, function (Argument $arg) {
            return $arg->name->value;
        }) : [];

        foreach ($argDefs as $argDef) {
            $name = $argDef->name;
            $argType = $argDef->getType();
            $argumentAST = isset($argASTMap[$name]) ? $argASTMap[$name] : null;

            if (!$argumentAST) {
                if ($argDef->defaultValueExists()) {
                    $coercedValues[$name] = $argDef->defaultValue;
                } else if ($argType instanceof NonNull) {
                    throw new Error(
                        'Argument "' . $name . '" of required type ' .
                        '"' . Utils::printSafe($argType) . '" was not provided.',
                        [$node]
                    );
                }
            } else if ($argumentAST->value instanceof Variable) {
                $variableName = $argumentAST->value->name->value;

                if ($variableValues && array_key_exists($variableName, $variableValues)) {
                    // Note: this does not check that this variable value is correct.
                    // This assumes that this query has been validated and the variable
                    // usage here is of the correct type.
                    $coercedValues[$name] = $variableValues[$variableName];
                } else if ($argDef->defaultValueExists()) {
                    $coercedValues[$name] = $argDef->defaultValue;
                } else if ($argType instanceof NonNull) {
                    throw new Error(
                        'Argument "' . $name . '" of required type "' . Utils::printSafe($argType) . '" was ' .
                        'provided the variable "$' . $variableName . '" which was not provided ' .
                        'a runtime value.',
                        [ $argumentAST->value ]
                    );
                }
            } else {
                $valueAST = $argumentAST->value;
                $coercedValue = Utils\AST::valueFromAST($valueAST, $argType, $variableValues);
                if ($coercedValue === $undefined) {
                    $errors = DocumentValidator::isValidLiteralValue($argType, $valueAST);
                    $message = !empty($errors) ? ("\n" . implode("\n", $errors)) : '';
                    throw new Error(
                        'Argument "' . $name . '" got invalid value ' . Printer::doPrint($valueAST) . '.' . $message,
                        [ $argumentAST->value ]
                    );
                }
                $coercedValues[$name] = $coercedValue;
            }
        }
        return $coercedValues;
    }

    /**
     * @deprecated Moved to Utils\AST::valueFromAST
     *
     * @param $valueAST
     * @param InputType $type
     * @param null $variables
     * @return array|null|\stdClass
     */
    public static function valueFromAST($valueAST, InputType $type, $variables = null)
    {
        return Utils\AST::valueFromAST($valueAST, $type, $variables);
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
            if (null === $value) {
                return ['Expected "' . Utils::printSafe($type) . '", found null.'];
            }
            return self::isValidPHPValue($value, $type->getWrappedType());
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
        $undefined = Utils::undefined();
        if ($value === $undefined) {
            return $undefined;
        }

        if ($type instanceof NonNull) {
            if ($value === null) {
                // Intentionally return no value.
                return $undefined;
            }
            return self::coerceValue($type->getWrappedType(), $value);
        }

        if (null === $value) {
            return null;
        }

        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            if (is_array($value) || $value instanceof \Traversable) {
                $coercedValues = [];
                foreach ($value as $item) {
                    $itemValue = self::coerceValue($itemType, $item);
                    if ($undefined === $itemValue) {
                        // Intentionally return no value.
                        return $undefined;
                    }
                    $coercedValues[] = $itemValue;
                }
                return $coercedValues;
            } else {
                $coercedValue = self::coerceValue($itemType, $value);
                if ($coercedValue === $undefined) {
                    // Intentionally return no value.
                    return $undefined;
                }
                return [$coercedValue];
            }
        }

        if ($type instanceof InputObjectType) {
            $coercedObj = [];
            $fields = $type->getFields();
            foreach ($fields as $fieldName => $field) {
                if (!array_key_exists($fieldName, $value)) {
                    if ($field->defaultValueExists()) {
                        $coercedObj[$fieldName] = $field->defaultValue;
                    } else if ($field->getType() instanceof NonNull) {
                        // Intentionally return no value.
                        return $undefined;
                    }
                    continue;
                }
                $fieldValue = self::coerceValue($field->getType(), $value[$fieldName]);
                if ($fieldValue === $undefined) {
                    // Intentionally return no value.
                    return $undefined;
                }
                $coercedObj[$fieldName] = $fieldValue;
            }
            return $coercedObj;
        }

        if ($type instanceof LeafType) {
            $parsed = $type->parseValue($value);
            if (null === $parsed) {
                // null or invalid values represent a failure to parse correctly,
                // in which case no value is returned.
                return $undefined;
            }
            return $parsed;
        }

        throw new InvariantViolation('Must be input type');
    }
}
