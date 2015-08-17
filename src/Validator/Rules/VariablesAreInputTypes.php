<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\VariableDefinition;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils;
use GraphQL\Validator\ValidationContext;

class VariablesAreInputTypes
{
    static function nonInputTypeOnVarMessage($variableName, $typeName)
    {
        return "Variable \"\$$variableName\" cannot be non-input type \"$typeName\".";
    }

    public function __invoke(ValidationContext $context)
    {
        return [
            Node::VARIABLE_DEFINITION => function(VariableDefinition $node) use ($context) {
                $type = Utils\TypeInfo::typeFromAST($context->getSchema(), $node->type);

                // If the variable type is not an input type, return an error.
                if ($type && !Type::isInputType($type)) {
                    $variableName = $node->variable->name->value;
                    return new Error(
                        self::nonInputTypeOnVarMessage($variableName, Printer::doPrint($node->type)),
                        [ $node->type ]
                    );
                }
            }
        ];
    }
}
