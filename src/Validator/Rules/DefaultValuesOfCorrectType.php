<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\ValidationContext;

class DefaultValuesOfCorrectType extends AbstractValidationRule
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

    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::VARIABLE_DEFINITION => function(VariableDefinitionNode $varDefNode) use ($context) {
                $name = $varDefNode->variable->name->value;
                $defaultValue = $varDefNode->defaultValue;
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
                        $context->reportError(new Error(
                            static::badValueForDefaultArgMessage($name, $type, Printer::doPrint($defaultValue), $errors),
                            [$defaultValue]
                        ));
                    }
                }
                return Visitor::skipNode();
            },
            NodeKind::SELECTION_SET => function() {return Visitor::skipNode();},
            NodeKind::FRAGMENT_DEFINITION => function() {return Visitor::skipNode();}
        ];
    }
}
