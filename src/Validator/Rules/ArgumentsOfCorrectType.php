<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\ValidationContext;

class ArgumentsOfCorrectType extends AbstractValidationRule
{
    static function badValueMessage($argName, $type, $value, $verboseErrors = [])
    {
        $message = $verboseErrors ? ("\n" . implode("\n", $verboseErrors)) : '';
        return "Argument \"$argName\" has invalid value $value.$message";
    }

    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::ARGUMENT => function(ArgumentNode $argNode) use ($context) {
                $argDef = $context->getArgument();
                if ($argDef) {
                    $errors = DocumentValidator::isValidLiteralValue($argDef->getType(), $argNode->value);

                    if (!empty($errors)) {
                        $context->reportError(new Error(
                            self::badValueMessage($argNode->name->value, $argDef->getType(), Printer::doPrint($argNode->value), $errors),
                            [$argNode->value]
                        ));
                    }
                }
                return Visitor::skipNode();
            }
        ];
    }
}
