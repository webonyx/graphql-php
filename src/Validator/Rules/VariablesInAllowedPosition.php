<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\OperationDefinition;
use GraphQL\Language\AST\Variable;
use GraphQL\Language\AST\VariableDefinition;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Utils\TypeInfo;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class VariablesInAllowedPosition
{
    static function badVarPosMessage($varName, $varType, $expectedType)
    {
        return "Variable \$$varName of type $varType used in position expecting ".
        "type $expectedType.";
    }

    public $varDefMap;

    public function __invoke(ValidationContext $context)
    {
        $varDefMap = [];

        return [
            Node::OPERATION_DEFINITION => [
                'enter' => function () {
                    $this->varDefMap = [];
                },
                'leave' => function(OperationDefinition $operation) use ($context) {
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

                            if ($varType && !TypeInfo::isTypeSubTypeOf($schema, $this->effectiveType($varType, $varDef), $type)) {
                                $context->reportError(new Error(
                                    self::badVarPosMessage($varName, $varType, $type),
                                    [$varDef, $node]
                                ));
                            }
                        }
                    }
                }
            ],
            Node::VARIABLE_DEFINITION => function (VariableDefinition $varDefAST) {
                $this->varDefMap[$varDefAST->variable->name->value] = $varDefAST;
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
