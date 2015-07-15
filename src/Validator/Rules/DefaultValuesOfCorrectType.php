<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\VariableDefinition;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class DefaultValuesOfCorrectType
{
    public function __invoke(ValidationContext $context)
    {
        return [
            Node::VARIABLE_DEFINITION => function(VariableDefinition $varDefAST) use ($context) {
                $name = $varDefAST->variable->name->value;
                $defaultValue = $varDefAST->defaultValue;
                $type = $context->getInputType();

                if ($type instanceof NonNull && $defaultValue) {
                    return new Error(
                        Messages::defaultForNonNullArgMessage($name, $type, $type->getWrappedType()),
                        [$defaultValue]
                    );
                }
                if ($type && $defaultValue && !DocumentValidator::isValidLiteralValue($defaultValue, $type)) {
                    return new Error(
                        Messages::badValueForDefaultArgMessage($name, $type, Printer::doPrint($defaultValue)),
                        [$defaultValue]
                    );
                }
                return null;
            }
        ];
    }
}
