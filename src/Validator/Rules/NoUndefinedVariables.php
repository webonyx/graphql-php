<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\Node;
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
    static function undefinedVarMessage($varName)
    {
        return "Variable \"$$varName\" is not defined.";
    }

    static function undefinedVarByOpMessage($varName, $opName)
    {
        return "Variable \"$$varName\" is not defined by operation \"$opName\".";
    }

    public function __invoke(ValidationContext $context)
    {
        $operation = null;
        $visitedFragmentNames = [];
        $definedVariableNames = [];

        return [
            // Visit FragmentDefinition after visiting FragmentSpread
            'visitSpreadFragments' => true,

            Node::OPERATION_DEFINITION => function(OperationDefinition $node, $key, $parent, $path, $ancestors) use (&$operation, &$visitedFragmentNames, &$definedVariableNames) {
                $operation = $node;
                $visitedFragmentNames = [];
                $definedVariableNames = [];
            },
            Node::VARIABLE_DEFINITION => function(VariableDefinition $def) use (&$definedVariableNames) {
                $definedVariableNames[$def->variable->name->value] = true;
            },
            Node::VARIABLE => function(Variable $variable, $key, $parent, $path, $ancestors) use (&$definedVariableNames, &$visitedFragmentNames, &$operation) {
                $varName = $variable->name->value;
                if (empty($definedVariableNames[$varName])) {
                    $withinFragment = false;
                    foreach ($ancestors as $ancestor) {
                        if ($ancestor instanceof FragmentDefinition) {
                            $withinFragment = true;
                            break;
                        }
                    }
                    if ($withinFragment && $operation && $operation->name) {
                        return new Error(
                            self::undefinedVarByOpMessage($varName, $operation->name->value),
                            [$variable, $operation]
                        );
                    }
                    return new Error(
                        self::undefinedVarMessage($varName),
                        [$variable]
                    );
                }
            },
            Node::FRAGMENT_SPREAD => function(FragmentSpread $spreadAST) use (&$visitedFragmentNames) {
                // Only visit fragments of a particular name once per operation
                if (!empty($visitedFragmentNames[$spreadAST->name->value])) {
                    return Visitor::skipNode();
                }
                $visitedFragmentNames[$spreadAST->name->value] = true;
            }
        ];
    }
}
