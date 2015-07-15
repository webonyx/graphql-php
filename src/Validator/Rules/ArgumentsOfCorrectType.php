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
    public function __invoke(ValidationContext $context)
    {
        return [
            Node::FIELD => function(Field $fieldAST) use ($context) {
                $fieldDef = $context->getFieldDef();
                if (!$fieldDef) {
                    return Visitor::skipNode();
                }
                $errors = [];
                $argASTs = $fieldAST->arguments ?: [];
                $argASTMap = Utils::keyMap($argASTs, function (Argument $arg) {
                    return $arg->name->value;
                });

                foreach ($fieldDef->args as $argDef) {
                    $argAST = isset($argASTMap[$argDef->name]) ? $argASTMap[$argDef->name] : null;
                    if (!$argAST && $argDef->getType() instanceof NonNull) {
                        $errors[] = new Error(
                            Messages::missingArgMessage(
                                $fieldAST->name->value,
                                $argDef->name,
                                $argDef->getType()
                            ),
                            [$fieldAST]
                        );
                    }
                }

                $argDefMap = Utils::keyMap($fieldDef->args, function ($def) {
                    return $def->name;
                });
                foreach ($argASTs as $argAST) {
                    $argDef = $argDefMap[$argAST->name->value];
                    if ($argDef && !DocumentValidator::isValidLiteralValue($argAST->value, $argDef->getType())) {
                        $errors[] = new Error(
                            Messages::badValueMessage(
                                $argAST->name->value,
                                $argDef->getType(),
                                Printer::doPrint($argAST->value)
                            ),
                            [$argAST->value]
                        );
                    }
                }

                return !empty($errors) ? $errors : null;
            }
        ];
    }
}
