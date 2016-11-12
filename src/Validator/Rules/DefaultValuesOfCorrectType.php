<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\AST\VariableDefinition;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\ValidationContext;

class DefaultValuesOfCorrectType
{
    static function badValueForDefaultArgMessage($varName, $type, $value, $verboseErrors = null)
    {
        $message = $verboseErrors ? ("\n" . implode("\n", $verboseErrors)) : '';
        return "Variable \$$varName has invalid default value: $value.$message";
    }

    static function defaultForNonNullArgMessage($varName, $type, $guessType)
    {
        return "Variable \$$varName of type $type " .
        "is required and will never use the default value. " .
        "Perhaps you meant to use type $guessType.";
    }

    public function __invoke(ValidationContext $context)
    {
        return [
            NodeType::VARIABLE_DEFINITION => function(VariableDefinition $varDefAST) use ($context) {
                $name = $varDefAST->getVariable()->getName()->getValue();
                $defaultValue = $varDefAST->getDefaultValue();
                $type = $context->getInputType();

                if ($type instanceof NonNull && $defaultValue) {
                    $context->reportError(new Error(
                        static::defaultForNonNullArgMessage($name, $type, $type->getWrappedType()),
                        [$defaultValue]
                    ));
                }
                if ($type && $defaultValue) {
                    $errors = DocumentValidator::isValidLiteralValue($type, $defaultValue);
                    if (!empty($errors)) {
                        $printer = new Printer();
                        $context->reportError(new Error(
                            static::badValueForDefaultArgMessage($name, $type, $printer->doPrint($defaultValue), $errors),
                            [$defaultValue]
                        ));
                    }
                }
                return Visitor::skipNode();
            },
            NodeType::SELECTION_SET => function() {return Visitor::skipNode();},
            NodeType::FRAGMENT_DEFINITION => function() {return Visitor::skipNode();}
        ];
    }
}
