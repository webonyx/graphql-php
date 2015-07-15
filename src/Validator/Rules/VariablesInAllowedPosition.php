<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\Node;
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
    public function __invoke(ValidationContext $context)
    {
        $varDefMap = new \ArrayObject();
        $visitedFragmentNames = new \ArrayObject();

        return [
            // Visit FragmentDefinition after visiting FragmentSpread
            'visitSpreadFragments' => true,
            Node::OPERATION_DEFINITION => function () use (&$varDefMap, &$visitedFragmentNames) {
                $varDefMap = new \ArrayObject();
                $visitedFragmentNames = new \ArrayObject();
            },
            Node::VARIABLE_DEFINITION => function (VariableDefinition $varDefAST) use ($varDefMap) {
                $varDefMap[$varDefAST->variable->name->value] = $varDefAST;
            },
            Node::FRAGMENT_SPREAD => function (FragmentSpread $spreadAST) use ($visitedFragmentNames) {
                // Only visit fragments of a particular name once per operation
                if (!empty($visitedFragmentNames[$spreadAST->name->value])) {
                    return Visitor::skipNode();
                }
                $visitedFragmentNames[$spreadAST->name->value] = true;
            },
            Node::VARIABLE => function (Variable $variableAST) use ($context, $varDefMap) {
                $varName = $variableAST->name->value;
                $varDef = isset($varDefMap[$varName]) ? $varDefMap[$varName] : null;
                $varType = $varDef ? TypeInfo::typeFromAST($context->getSchema(), $varDef->type) : null;
                $inputType = $context->getInputType();

                if ($varType && $inputType &&
                    !$this->varTypeAllowedForType($this->effectiveType($varType, $varDef), $inputType)
                ) {
                    return new Error(
                        Messages::badVarPosMessage($varName, $varType, $inputType),
                        [$variableAST]
                    );
                }
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
