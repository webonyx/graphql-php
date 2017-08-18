<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\TypeInfo;
use GraphQL\Validator\ValidationContext;

class VariablesAreInputTypes extends AbstractValidationRule
{
    static function nonInputTypeOnVarMessage($variableName, $typeName)
    {
        return "Variable \"\$$variableName\" cannot be non-input type \"$typeName\".";
    }

    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::VARIABLE_DEFINITION => function(VariableDefinitionNode $node) use ($context) {
                $type = TypeInfo::typeFromAST($context->getSchema(), $node->type);

                // If the variable type is not an input type, return an error.
                if ($type && !Type::isInputType($type)) {
                    $variableName = $node->variable->name->value;
                    $context->reportError(new Error(
                        self::nonInputTypeOnVarMessage($variableName, Printer::doPrint($node->type)),
                        [ $node->type ]
                    ));
                }
            }
        ];
    }
}
