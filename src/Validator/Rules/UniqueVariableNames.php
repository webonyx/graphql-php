<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Validator\QueryValidationContext;

class UniqueVariableNames extends ValidationRule
{
    /** @var array<string, NameNode> */
    protected array $knownVariableNames;

    public function getVisitor(QueryValidationContext $context): array
    {
        $this->knownVariableNames = [];

        return [
            NodeKind::OPERATION_DEFINITION => function (): void {
                $this->knownVariableNames = [];
            },
            NodeKind::VARIABLE_DEFINITION => function (VariableDefinitionNode $node) use ($context): void {
                $variableName = $node->variable->name->value;
                if (! isset($this->knownVariableNames[$variableName])) {
                    $this->knownVariableNames[$variableName] = $node->variable->name;
                } else {
                    $context->reportError(new Error(
                        static::duplicateVariableMessage($variableName),
                        [$this->knownVariableNames[$variableName], $node->variable->name]
                    ));
                }
            },
        ];
    }

    public static function duplicateVariableMessage(string $variableName): string
    {
        return "There can be only one variable named \"{$variableName}\".";
    }
}
