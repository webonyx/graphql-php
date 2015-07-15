<?php
namespace GraphQL\Executor;


use GraphQL\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\VariableDefinition;
use GraphQL\Language\Printer;
use GraphQL\Schema;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputObjectType;
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
     */
    public static function getVariableValues(Schema $schema, /* Array<VariableDefinition> */ $definitionASTs, array $inputs)
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
     */
    public static function getArgumentValues(/* Array<GraphQLFieldArgument>*/ $argDefs, /*Array<Argument>*/ $argASTs, $variables)
    {
        if (!$argDefs || count($argDefs) === 0) {
            return null;
        }
        $argASTMap = $argASTs ? Utils::keyMap($argASTs, function ($arg) {
            return $arg->name->value;
        }) : [];
        $result = [];
        foreach ($argDefs as $argDef) {
            $name = $argDef->name;
            $valueAST = isset($argASTMap[$name]) ? $argASTMap[$name]->value : null;
            $result[$name] = self::coerceValueAST($argDef->getType(), $valueAST, $variables);
        }
        return $result;
    }

    public static function getDirectiveValue(Directive $directiveDef, /* Array<Directive> */ $directives, $variables)
    {
        $directiveAST = null;
        if ($directives) {
            foreach ($directives as $directive) {
                if ($directive->name->value === $directiveDef->name) {
                    $directiveAST = $directive;
                    break;
                }
            }
        }
        if ($directiveAST) {
            if (!$directiveDef->type) {
                return null;
            }
            return self::coerceValueAST($directiveDef->type, $directiveAST->value, $variables);
        }
    }

    /**
     * Given a variable definition, and any value of input, return a value which
     * adheres to the variable definition, or throw an error.
     */
    private static function getVariableValue(Schema $schema, VariableDefinition $definitionAST, $input)
    {
        $type = Utils\TypeInfo::typeFromAST($schema, $definitionAST->type);
        if (!$type) {
            return null;
        }
        if (self::isValidValue($type, $input)) {
            if (null === $input) {
                $defaultValue = $definitionAST->defaultValue;
                if ($defaultValue) {
                    return self::coerceValueAST($type, $defaultValue);
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
     * Given a type and any value, return true if that value is valid.
     */
    private static function isValidValue(Type $type, $value)
    {
        if ($type instanceof NonNull) {
            if (null === $value) {
                return false;
            }
            return self::isValidValue($type->getWrappedType(), $value);
        }

        if ($value === null) {
            return true;
        }

        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            if (is_array($value)) {
                foreach ($value as $item) {
                    if (!self::isValidValue($itemType, $item)) {
                        return false;
                    }
                }
                return true;
            } else {
                return self::isValidValue($itemType, $value);
            }
        }

        if ($type instanceof InputObjectType) {
            $fields = $type->getFields();
            foreach ($fields as $fieldName => $field) {
                /** @var FieldDefinition $field */
                if (!self::isValidValue($field->getType(), isset($value[$fieldName]) ? $value[$fieldName] : null)) {
                    return false;
                }
            }
            return true;
        }

        if ($type instanceof ScalarType ||
            $type instanceof EnumType
        ) {
            return null !== $type->coerce($value);
        }

        return false;
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
                $fieldValue = self::coerceValue($field->getType(), $value[$fieldName]);
                $obj[$fieldName] = $fieldValue === null ? $field->defaultValue : $fieldValue;
            }
            return $obj;

        }

        if ($type instanceof ScalarType ||
            $type instanceof EnumType
        ) {
            $coerced = $type->coerce($value);
            if (null !== $coerced) {
                return $coerced;
            }
        }

        return null;
    }

    /**
     * Given a type and a value AST node known to match this type, build a
     * runtime value.
     */
    private static function coerceValueAST(Type $type, $valueAST, $variables)
    {
        if ($type instanceof NonNull) {
            // Note: we're not checking that the result of coerceValueAST is non-null.
            // We're assuming that this query has been validated and the value used
            // here is of the correct type.
            return self::coerceValueAST($type->getWrappedType(), $valueAST, $variables);
        }

        if (!$valueAST) {
            return null;
        }

        if ($valueAST->kind === Node::VARIABLE) {
            $variableName = $valueAST->name->value;

            if (!isset($variables, $variables[$variableName])) {
                return null;
            }

            // Note: we're not doing any checking that this variable is correct. We're
            // assuming that this query has been validated and the variable usage here
            // is of the correct type.
            return $variables[$variableName];
        }

        if ($type instanceof ListOfType) {
            $itemType = $type->getWrappedType();
            if ($valueAST->kind === Node::ARR) {
                $tmp = [];
                foreach ($valueAST->values as $itemAST) {
                    $tmp[] = self::coerceValueAST($itemType, $itemAST, $variables);
                }
                return $tmp;
            } else {
                return [self::coerceValueAST($itemType, $valueAST, $variables)];
            }
        }

        if ($type instanceof InputObjectType) {
            $fields = $type->getFields();
            if ($valueAST->kind !== Node::OBJECT) {
                return null;
            }
            $fieldASTs = Utils::keyMap($valueAST->fields, function ($field) {
                return $field->name->value;
            });

            $obj = [];
            foreach ($fields as $fieldName => $field) {
                $fieldAST = $fieldASTs[$fieldName];
                $fieldValue = self::coerceValueAST($field->getType(), $fieldAST ? $fieldAST->value : null, $variables);
                $obj[$fieldName] = $fieldValue === null ? $field->defaultValue : $fieldValue;
            }
            return $obj;
        }

        if ($type instanceof ScalarType || $type instanceof EnumType) {
            $coerced = $type->coerceLiteral($valueAST);
            if (null !== $coerced) {
                return $coerced;
            }
        }

        return null;
    }
}
