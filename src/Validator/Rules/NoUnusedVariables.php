<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\Visitor;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class NoUnusedVariables
{
    static function unusedVariableMessage($varName)
    {
        return "Variable \"$$varName\" is never used.";
    }

    public function __invoke(ValidationContext $context)
    {
        $visitedFragmentNames = new \stdClass();
        $variableDefs = [];
        $variableNameUsed = new \stdClass();

        return [
            // Visit FragmentDefinition after visiting FragmentSpread
            'visitSpreadFragments' => true,
            Node::OPERATION_DEFINITION => [
                'enter' => function() use (&$visitedFragmentNames, &$variableDefs, &$variableNameUsed) {
                    $visitedFragmentNames = new \stdClass();
                    $variableDefs = [];
                    $variableNameUsed = new \stdClass();
                },
                'leave' => function() use (&$visitedFragmentNames, &$variableDefs, &$variableNameUsed) {
                    $errors = [];
                    foreach ($variableDefs as $def) {
                        if (empty($variableNameUsed->{$def->variable->name->value})) {
                            $errors[] = new Error(
                                self::unusedVariableMessage($def->variable->name->value),
                                [$def]
                            );
                        }
                    }
                    return !empty($errors) ? $errors : null;
                }
            ],
            Node::VARIABLE_DEFINITION => function($def) use (&$variableDefs) {
                $variableDefs[] = $def;
                return Visitor::skipNode();
            },
            Node::VARIABLE => function($variable) use (&$variableNameUsed) {
                $variableNameUsed->{$variable->name->value} = true;
            },
            Node::FRAGMENT_SPREAD => function($spreadAST) use (&$visitedFragmentNames) {
                // Only visit fragments of a particular name once per operation
                if (!empty($visitedFragmentNames->{$spreadAST->name->value})) {
                    return Visitor::skipNode();
                }
                $visitedFragmentNames->{$spreadAST->name->value} = true;
            }
        ];
    }
}
