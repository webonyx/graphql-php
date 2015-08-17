<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\Argument;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\Node;
use GraphQL\Language\Printer;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Utils;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class ArgumentsOfCorrectType
{
    static function badValueMessage($argName, $type, $value)
    {
        return "Argument \"$argName\" expected type \"$type\" but got: $value.";
    }

    public function __invoke(ValidationContext $context)
    {
        return [
            Node::ARGUMENT => function(Argument $argAST) use ($context) {
                $argDef = $context->getArgument();
                if ($argDef && !DocumentValidator::isValidLiteralValue($argAST->value, $argDef->getType())) {
                    return new Error(
                        self::badValueMessage($argAST->name->value, $argDef->getType(), Printer::doPrint($argAST->value)),
                        [$argAST->value]
                    );
                }
            }
        ];
    }
}
