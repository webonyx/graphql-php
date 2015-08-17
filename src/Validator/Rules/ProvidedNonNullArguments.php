<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\Directive;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\Node;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Utils;
use GraphQL\Validator\ValidationContext;

class ProvidedNonNullArguments
{
    static function missingFieldArgMessage($fieldName, $argName, $type)
    {
        return "Field \"$fieldName\" argument \"$argName\" of type \"$type\" is required but not provided.";
    }

    static function missingDirectiveArgMessage($directiveName, $argName, $type)
    {
        return "Directive \"@$directiveName\" argument \"$argName\" of type \"$type\" is required but not provided.";
    }

    public function __invoke(ValidationContext $context)
    {
        return [
            Node::FIELD => [
                'leave' => function(Field $fieldAST) use ($context) {
                    $fieldDef = $context->getFieldDef();

                    if (!$fieldDef) {
                        return Visitor::skipNode();
                    }
                    $errors = [];
                    $argASTs = $fieldAST->arguments ?: [];

                    $argASTMap = [];
                    foreach ($argASTs as $argAST) {
                        $argASTMap[$argAST->name->value] = $argASTs;
                    }
                    foreach ($fieldDef->args as $argDef) {
                        $argAST = isset($argASTMap[$argDef->name]) ? $argASTMap[$argDef->name] : null;
                        if (!$argAST && $argDef->getType() instanceof NonNull) {
                            $errors[] = new Error(
                                self::missingFieldArgMessage($fieldAST->name->value, $argDef->name, $argDef->getType()),
                                [$fieldAST]
                            );
                        }
                    }

                    if (!empty($errors)) {
                        return $errors;
                    }
                }
            ],
            Node::DIRECTIVE => [
                'leave' => function(Directive $directiveAST) use ($context) {
                    $directiveDef = $context->getDirective();
                    if (!$directiveDef) {
                        return Visitor::skipNode();
                    }
                    $errors = [];
                    $argASTs = $directiveAST->arguments ?: [];
                    $argASTMap = [];
                    foreach ($argASTs as $argAST) {
                        $argASTMap[$argAST->name->value] = $argASTs;
                    }

                    foreach ($directiveDef->args as $argDef) {
                        $argAST = isset($argASTMap[$argDef->name]) ? $argASTMap[$argDef->name] : null;
                        if (!$argAST && $argDef->getType() instanceof NonNull) {
                            $errors[] = new Error(
                                self::missingDirectiveArgMessage($directiveAST->name->value, $argDef->name, $argDef->getType()),
                                [$directiveAST]
                            );
                        }
                    }
                    if (!empty($errors)) {
                        return $errors;
                    }
                }
            ]
        ];
    }
}
