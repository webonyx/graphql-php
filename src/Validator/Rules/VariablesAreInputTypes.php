<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\TypeInfo;
use GraphQL\Validator\ValidationContext;

class VariablesAreInputTypes extends ValidationRule
{
    public function getVisitor(ValidationContext $context): array
    {
        return [
            NodeKind::VARIABLE_DEFINITION => static function (VariableDefinitionNode $node) use ($context): void {
                $type = TypeInfo::typeFromAST($context->getSchema(), $node->type);

                // If the variable type is not an input type, return an error.
                if (null === $type || Type::isInputType($type)) {
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
