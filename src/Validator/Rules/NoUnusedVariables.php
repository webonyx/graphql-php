<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\Visitor;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class NoUnusedVariables
{
    static function unusedVariableMessage($varName, $opName = null)
    {
        return $opName
            ? "Variable \"$$varName\" is never used in operation \"$opName\"."
            : "Variable \"$$varName\" is never used.";
    }

    public $variableDefs;

    public function __invoke(ValidationContext $context)
    {
        $this->variableDefs = [];

        return [
            NodeType::OPERATION_DEFINITION => [
                'enter' => function() {
                    $this->variableDefs = [];
                },
                'leave' => function(OperationDefinition $operation) use ($context) {
                    $variableNameUsed = [];
                    $usages = $context->getRecursiveVariableUsages($operation);
                    $opName = $operation->getName() ? $operation->getName()->getValue() : null;

                    foreach ($usages as $usage) {
                        $node = $usage['node'];
                        $variableNameUsed[$node->getName()->getValue()] = true;
                    }

                    foreach ($this->variableDefs as $variableDef) {
                        $variableName = $variableDef->getVariable()->getName()->getValue();

                        if (empty($variableNameUsed[$variableName])) {
                            $context->reportError(new Error(
                                self::unusedVariableMessage($variableName, $opName),
                                [$variableDef]
                            ));
                        }
                    }
                }
            ],
            NodeType::VARIABLE_DEFINITION => function($def) {
                $this->variableDefs[] = $def;
            }
        ];
    }
}
