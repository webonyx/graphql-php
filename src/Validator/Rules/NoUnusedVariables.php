<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Validator\ValidationContext;

class NoUnusedVariables extends AbstractValidationRule
{
    static function unusedVariableMessage($varName, $opName = null)
    {
        return $opName
            ? "Variable \"$$varName\" is never used in operation \"$opName\"."
            : "Variable \"$$varName\" is never used.";
    }

    public $variableDefs;

    public function getVisitor(ValidationContext $context)
    {
        $this->variableDefs = [];

        return [
            NodeKind::OPERATION_DEFINITION => [
                'enter' => function() {
                    $this->variableDefs = [];
                },
                'leave' => function(OperationDefinitionNode $operation) use ($context) {
                    $variableNameUsed = [];
                    $usages = $context->getRecursiveVariableUsages($operation);
                    $opName = $operation->name ? $operation->name->value : null;

                    foreach ($usages as $usage) {
                        $node = $usage['node'];
                        $variableNameUsed[$node->name->value] = true;
                    }

                    foreach ($this->variableDefs as $variableDef) {
                        $variableName = $variableDef->variable->name->value;

                        if (empty($variableNameUsed[$variableName])) {
                            $context->reportError(new Error(
                                self::unusedVariableMessage($variableName, $opName),
                                [$variableDef]
                            ));
                        }
                    }
                }
            ],
            NodeKind::VARIABLE_DEFINITION => function($def) {
                $this->variableDefs[] = $def;
            }
        ];
    }
}
