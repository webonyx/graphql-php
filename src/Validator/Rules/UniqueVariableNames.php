<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\AST\VariableDefinition;
use GraphQL\Validator\ValidationContext;

class UniqueVariableNames
{
    static function duplicateVariableMessage($variableName)
    {
        return "There can be only one variable named \"$variableName\".";
    }

    public $knownVariableNames;

    public function __invoke(ValidationContext $context)
    {
        $this->knownVariableNames = [];

        return [
            NodeType::OPERATION_DEFINITION => function() {
                $this->knownVariableNames = [];
            },
            NodeType::VARIABLE_DEFINITION => function(VariableDefinition $node) use ($context) {
                $variableName = $node->getVariable()->getName()->getValue();
                if (!empty($this->knownVariableNames[$variableName])) {
                    $context->reportError(new Error(
                        self::duplicateVariableMessage($variableName),
                        [ $this->knownVariableNames[$variableName], $node->getVariable()->getName() ]
                    ));
                } else {
                    $this->knownVariableNames[$variableName] = $node->getVariable()->getName();
                }
            }
        ];
    }
}
