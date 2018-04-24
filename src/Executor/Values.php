<?php
namespace GraphQL\Executor;

use GraphQL\Error\Error;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\Printer;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\AST;
use GraphQL\Utils\TypeInfo;
use GraphQL\Utils\Utils;
use GraphQL\Utils\Value;

class Values
{
    /**
     * Prepares an object map of variables of the correct type based on the provided
     * variable definitions and arbitrary input. If the input cannot be coerced
     * to match the variable definitions, a Error will be thrown.
     *
     * @param Schema $schema
     * @param VariableDefinitionNode[] $varDefNodes
     * @param array $inputs
     * @return array
     */
    public static function getVariableValues(Schema $schema, $varDefNodes, array $inputs)
    {
        $errors = [];
        $coercedValues = [];
        foreach ($varDefNodes as $varDefNode) {
            $varName = $varDefNode->variable->name->value;
            /** @var InputType|Type $varType */
            $varType = TypeInfo::typeFromAST($schema, $varDefNode->type);

            if (!Type::isInputType($varType)) {
                $errors[] = new Error(
                    "Variable \"\$$varName\" expected value of type " .
                    '"' . Printer::doPrint($varDefNode->type) . '" which cannot be used as an input type.',
                    [$varDefNode->type]
                );
            } else {
                if (!array_key_exists($varName, $inputs)) {
                    if ($varType instanceof NonNull) {
                        $errors[] = new Error(
                            "Variable \"\$$varName\" of required type " .
                            "\"{$varType}\" was not provided.",
                            [$varDefNode]
                        );
                    } else if ($varDefNode->defaultValue) {
                        $coercedValues[$varName] = AST::valueFromAST($varDefNode->defaultValue, $varType);
                    }
                } else {
                    $value = $inputs[$varName];
                    $coerced = Value::coerceValue($value, $varType, $varDefNode);
                    /** @var Error[] $coercionErrors */
                    $coercionErrors = $coerced['errors'];
                    if ($coercionErrors) {
                        $messagePrelude = "Variable \"\$$varName\" got invalid value " . Utils::printSafeJson($value) . '; ';

                        foreach($coercionErrors as $error) {
                            $errors[] = new Error(
                                $messagePrelude . $error->getMessage(),
                                $error->getNodes(),
                                $error->getSource(),
                                $error->getPositions(),
                                $error->getPath(),
                                $error,
                                $error->getExtensions()
                            );
                        }
                    } else {
                        $coercedValues[$varName] = $coerced['value'];
                    }
                }
            }
        }
        return ['errors' => $errors, 'coerced' => $errors ? null : $coercedValues];
    }

    /**
     * Prepares an object map of argument values given a list of argument
     * definitions and list of argument AST nodes.
     *
     * @param FieldDefinition|Directive $def
     * @param FieldNode|\GraphQL\Language\AST\DirectiveNode $node
     * @param $variableValues
     * @return array
     * @throws Error
     */
    public static function getArgumentValues($def, $node, $variableValues = null)
    {
        $argDefs = $def->args;
        $argNodes = $node->arguments;

        if (!$argDefs || null === $argNodes) {
            return [];
        }

        $coercedValues = [];

        /** @var ArgumentNode[] $argNodeMap */
        $argNodeMap = $argNodes ? Utils::keyMap($argNodes, function (ArgumentNode $arg) {
            return $arg->name->value;
        }) : [];

        foreach ($argDefs as $argDef) {
            $name = $argDef->name;
            $argType = $argDef->getType();
            $argumentNode = isset($argNodeMap[$name]) ? $argNodeMap[$name] : null;

            if (!$argumentNode) {
                if ($argDef->defaultValueExists()) {
                    $coercedValues[$name] = $argDef->defaultValue;
                } else if ($argType instanceof NonNull) {
                    throw new Error(
                        'Argument "' . $name . '" of required type ' .
                        '"' . Utils::printSafe($argType) . '" was not provided.',
                        [$node]
                    );
                }
            } else if ($argumentNode->value instanceof VariableNode) {
                $variableName = $argumentNode->value->name->value;

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
                        [ $argumentNode->value ]
                    );
                }
            } else {
                $valueNode = $argumentNode->value;
                $coercedValue = AST::valueFromAST($valueNode, $argType, $variableValues);
                if (Utils::isInvalid($coercedValue)) {
                    // Note: ValuesOfCorrectType validation should catch this before
                    // execution. This is a runtime check to ensure execution does not
                    // continue with an invalid argument value.
                    throw new Error(
                        'Argument "' . $name . '" has invalid value ' . Printer::doPrint($valueNode) . '.',
                        [ $argumentNode->value ]
                    );
                }
                $coercedValues[$name] = $coercedValue;
            }
        }
        return $coercedValues;
    }

    /**
     * Prepares an object map of argument values given a directive definition
     * and a AST node which may contain directives. Optionally also accepts a map
     * of variable values.
     *
     * If the directive does not exist on the node, returns undefined.
     *
     * @param Directive $directiveDef
     * @param FragmentSpreadNode | FieldNode | InlineFragmentNode | EnumValueDefinitionNode | FieldDefinitionNode $node
     * @param array|null $variableValues
     *
     * @return array|null
     */
    public static function getDirectiveValues(Directive $directiveDef, $node, $variableValues = null)
    {
        if (isset($node->directives) && $node->directives instanceof NodeList) {
            $directiveNode = Utils::find($node->directives, function(DirectiveNode $directive) use ($directiveDef) {
                return $directive->name->value === $directiveDef->name;
            });

            if ($directiveNode) {
                return self::getArgumentValues($directiveDef, $directiveNode, $variableValues);
            }
        }
        return null;
    }

    /**
     * @deprecated as of 8.0 (Moved to \GraphQL\Utils\AST::valueFromAST)
     *
     * @param $valueNode
     * @param InputType $type
     * @param null $variables
     * @return array|null|\stdClass
     */
    public static function valueFromAST($valueNode, InputType $type, $variables = null)
    {
        return AST::valueFromAST($valueNode, $type, $variables);
    }

    /**
     * @deprecated as of 0.12 (Use coerceValue() directly for richer information)
     * @param $value
     * @param InputType $type
     * @return array
     */
    public static function isValidPHPValue($value, InputType $type)
    {
        $errors = Value::coerceValue($value, $type)['errors'];
        return $errors
            ? array_map(function(/*\Throwable */$error) { return $error->getMessage(); }, $errors)
            : [];
    }
}
