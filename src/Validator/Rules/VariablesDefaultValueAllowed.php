<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Validator\ValidationContext;

/**
 * Variable's default value is allowed
 *
 * A GraphQL document is only valid if all variable default values are allowed
 * due to a variable not being required.
 */
class VariablesDefaultValueAllowed extends AbstractValidationRule
{
    static function defaultForRequiredVarMessage($varName, $type, $guessType)
    {
        return (
            "Variable \"\${$varName}\" of type \"{$type}\" is required and " .
            'will not use the default value. ' .
            "Perhaps you meant to use type \"{$guessType}\"."
        );
    }

    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::VARIABLE_DEFINITION => function(VariableDefinitionNode $node) use ($context) {
                $name = $node->variable->name->value;
                $defaultValue = $node->defaultValue;
                $type = $context->getInputType();
                if ($type instanceof NonNull && $defaultValue) {
                    $context->reportError(
                      new Error(
                          self::defaultForRequiredVarMessage(
                              $name,
                              $type,
                              $type->getWrappedType()
                          ),
                          [$defaultValue]
                      )
                    );
                }

                return Visitor::skipNode();
            },
            NodeKind::SELECTION_SET => function(SelectionSetNode $node) use ($context) {
                return Visitor::skipNode();
            },
            NodeKind::FRAGMENT_DEFINITION => function(FragmentDefinitionNode $node) use ($context) {
                return Visitor::skipNode();
            },
        ];
    }
}
