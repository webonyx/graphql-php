<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Utils\TypeComparators;
use GraphQL\Utils\TypeInfo;
use GraphQL\Validator\ValidationContext;

class VariablesInAllowedPosition extends AbstractValidationRule
{
    static function badVarPosMessage($varName, $varType, $expectedType)
    {
        return "Variable \"\$$varName\" of type \"$varType\" used in position expecting ".
        "type \"$expectedType\".";
    }

    public $varDefMap;

    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::OPERATION_DEFINITION => [
                'enter' => function () {
                    $this->varDefMap = [];
                },
                'leave' => function(OperationDefinitionNode $operation) use ($context) {
                    $usages = $context->getRecursiveVariableUsages($operation);

                    foreach ($usages as $usage) {
                        $node = $usage['node'];
                        $type = $usage['type'];
                        $varName = $node->name->value;
                        $varDef = isset($this->varDefMap[$varName]) ? $this->varDefMap[$varName] : null;

                        if ($varDef && $type) {
                            // A var type is allowed if it is the same or more strict (e.g. is
                            // a subtype of) than the expected type. It can be more strict if
                            // the variable type is non-null when the expected type is nullable.
                            // If both are list types, the variable item type can be more strict
                            // than the expected item type (contravariant).
                            $schema = $context->getSchema();
                            $varType = TypeInfo::typeFromAST($schema, $varDef->type);

                            if ($varType && !TypeComparators::isTypeSubTypeOf($schema, $this->effectiveType($varType, $varDef), $type)) {
                                $context->reportError(new Error(
                                    self::badVarPosMessage($varName, $varType, $type),
                                    [$varDef, $node]
                                ));
                            }
                        }
                    }
                }
            ],
            NodeKind::VARIABLE_DEFINITION => function (VariableDefinitionNode $varDefNode) {
                $this->varDefMap[$varDefNode->variable->name->value] = $varDefNode;
            }
        ];
    }

    // A var type is allowed if it is the same or more strict than the expected
    // type. It can be more strict if the variable type is non-null when the
    // expected type is nullable. If both are list types, the variable item type can
    // be more strict than the expected item type.
    private function varTypeAllowedForType($varType, $expectedType)
    {
        if ($expectedType instanceof NonNull) {
            if ($varType instanceof NonNull) {
                return $this->varTypeAllowedForType($varType->getWrappedType(), $expectedType->getWrappedType());
            }
            return false;
        }
        if ($varType instanceof NonNull) {
            return $this->varTypeAllowedForType($varType->getWrappedType(), $expectedType);
        }
        if ($varType instanceof ListOfType && $expectedType instanceof ListOfType) {
            return $this->varTypeAllowedForType($varType->getWrappedType(), $expectedType->getWrappedType());
        }
        return $varType === $expectedType;
    }

    // If a variable definition has a default value, it's effectively non-null.
    private function effectiveType($varType, $varDef)
    {
        return (!$varDef->defaultValue || $varType instanceof NonNull) ? $varType : new NonNull($varType);
    }

}
