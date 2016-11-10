<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\AST\Variable;
use GraphQL\Language\AST\VariableDefinition;
use GraphQL\Language\Visitor;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

/**
 * Class NoUndefinedVariables
 *
 * A GraphQL operation is only valid if all variables encountered, both directly
 * and via fragment spreads, are defined by that operation.
 *
 * @package GraphQL\Validator\Rules
 */
class NoUndefinedVariables
{
    static function undefinedVarMessage($varName, $opName = null)
    {
        return $opName
            ? "Variable \"$$varName\" is not defined by operation \"$opName\"."
            : "Variable \"$$varName\" is not defined.";
    }

    public function __invoke(ValidationContext $context)
    {
        $variableNameDefined = [];

        return [
            NodeType::OPERATION_DEFINITION => [
                'enter' => function() use (&$variableNameDefined) {
                    $variableNameDefined = [];
                },
                'leave' => function(OperationDefinition $operation) use (&$variableNameDefined, $context) {
                    $usages = $context->getRecursiveVariableUsages($operation);

                    foreach ($usages as $usage) {
                        $node = $usage['node'];
                        $varName = $node->getName()->getValue();

                        if (empty($variableNameDefined[$varName])) {
                            $context->reportError(new Error(
                                self::undefinedVarMessage(
                                    $varName,
                                    $operation->getName() ? $operation->getName()->getValue() : null
                                ),
                                [ $node, $operation ]
                            ));
                        }
                    }
                }
            ],
            NodeType::VARIABLE_DEFINITION => function(VariableDefinition $def) use (&$variableNameDefined) {
                $variableNameDefined[$def->getVariable()->getName()->getValue()] = true;
            }
        ];
    }
}
