<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\Directive;
use GraphQL\Language\AST\Field;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
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
            NodeType::FIELD => [
                'leave' => function(Field $fieldAST) use ($context) {
                    $fieldDef = $context->getFieldDef();

                    if (!$fieldDef) {
                        return Visitor::skipNode();
                    }
                    $argASTs = $fieldAST->getArguments() ?: [];

                    $argASTMap = [];
                    foreach ($argASTs as $argAST) {
                        $argASTMap[$argAST->getName()->getValue()] = $argASTs;
                    }
                    foreach ($fieldDef->args as $argDef) {
                        $argAST = isset($argASTMap[$argDef->name]) ? $argASTMap[$argDef->name] : null;
                        if (!$argAST && $argDef->getType() instanceof NonNull) {
                            $context->reportError(new Error(
                                self::missingFieldArgMessage($fieldAST->getName()->getValue(), $argDef->name, $argDef->getType()),
                                [$fieldAST]
                            ));
                        }
                    }
                }
            ],
            NodeType::DIRECTIVE => [
                'leave' => function(Directive $directiveAST) use ($context) {
                    $directiveDef = $context->getDirective();
                    if (!$directiveDef) {
                        return Visitor::skipNode();
                    }
                    $argASTs = $directiveAST->getArguments() ?: [];
                    $argASTMap = [];
                    foreach ($argASTs as $argAST) {
                        $argASTMap[$argAST->getName()->getValue()] = $argASTs;
                    }

                    foreach ($directiveDef->args as $argDef) {
                        $argAST = isset($argASTMap[$argDef->name]) ? $argASTMap[$argDef->name] : null;
                        if (!$argAST && $argDef->getType() instanceof NonNull) {
                            $context->reportError(new Error(
                                self::missingDirectiveArgMessage($directiveAST->getName()->getValue(), $argDef->name, $argDef->getType()),
                                [$directiveAST]
                            ));
                        }
                    }
                }
            ]
        ];
    }
}
