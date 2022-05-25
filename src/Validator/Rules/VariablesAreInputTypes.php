<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\AST;
use GraphQL\Validator\QueryValidationContext;

class VariablesAreInputTypes extends ValidationRule
{
    public function getVisitor(QueryValidationContext $context): array
    {
        return [
            NodeKind::VARIABLE_DEFINITION => static function (VariableDefinitionNode $node) use ($context): void {
                $type = AST::typeFromAST([$context->getSchema(), 'getType'], $node->type);

                // If the variable type is not an input type, return an error.
                if ($type === null || Type::isInputType($type)) {
                    return;
                }

                $variableName = $node->variable->name->value;
                $context->reportError(new Error(
                    static::nonInputTypeOnVarMessage($variableName, Printer::doPrint($node->type)),
                    [$node->type]
                ));
            },
        ];
    }

    public static function nonInputTypeOnVarMessage(string $variableName, string $typeName): string
    {
        return "Variable \"\${$variableName}\" cannot be non-input type \"{$typeName}\".";
    }
}
