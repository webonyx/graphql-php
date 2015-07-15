<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\Type;
use GraphQL\Language\AST\VariableDefinition;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\InputType;
use GraphQL\Utils;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class VariablesAreInputTypes
{
    public function __invoke(ValidationContext $context)
    {
        return [
            Node::VARIABLE_DEFINITION => function(VariableDefinition $node) use ($context) {
                $typeName = $this->getTypeASTName($node->type);
                $type = $context->getSchema()->getType($typeName);

                if (!($type instanceof InputType)) {
                    $variableName = $node->variable->name->value;
                    return new Error(
                        Messages::nonInputTypeOnVarMessage($variableName, Printer::doPrint($node->type)),
                        [$node->type]
                    );
                }
            }
        ];
    }

    private function getTypeASTName(Type $typeAST)
    {
        if ($typeAST->kind === Node::NAME) {
            return $typeAST->value;
        }
        Utils::invariant($typeAST->type, 'Must be wrapping type');
        return $this->getTypeASTName($typeAST->type);
    }
}
