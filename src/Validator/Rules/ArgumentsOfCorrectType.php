<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Utils;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class ArgumentsOfCorrectType
{
    static function badValueMessage($argName, $type, $value, $verboseErrors = [])
    {
        $message = $verboseErrors ? ("\n" . implode("\n", $verboseErrors)) : '';
        return "Argument \"$argName\" has invalid value $value.$message";
    }

    public function __invoke(ValidationContext $context)
    {
        return [
            NodeType::ARGUMENT => function(Argument $argAST) use ($context) {
                $argDef = $context->getArgument();
                if ($argDef) {
                    $errors = DocumentValidator::isValidLiteralValue($argDef->getType(), $argAST->getValue());

                    if (!empty($errors)) {
                        $context->reportError(new Error(
                            self::badValueMessage($argAST->getName()->getValue(), $argDef->getType(), Printer::doPrint($argAST->getValue()), $errors),
                            [$argAST->getValue()]
                        ));
                    }
                }
                return Visitor::skipNode();
            }
        ];
    }
}
