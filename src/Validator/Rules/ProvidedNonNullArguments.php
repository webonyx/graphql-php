<?php
namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Validator\ValidationContext;

class ProvidedNonNullArguments extends AbstractValidationRule
{
    static function missingFieldArgMessage($fieldName, $argName, $type)
    {
        return "Field \"$fieldName\" argument \"$argName\" of type \"$type\" is required but not provided.";
    }

    static function missingDirectiveArgMessage($directiveName, $argName, $type)
    {
        return "Directive \"@$directiveName\" argument \"$argName\" of type \"$type\" is required but not provided.";
    }

    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::FIELD => [
                'leave' => function(FieldNode $fieldNode) use ($context) {
                    $fieldDef = $context->getFieldDef();

                    if (!$fieldDef) {
                        return Visitor::skipNode();
                    }
                    $argNodes = $fieldNode->arguments ?: [];

                    $argNodeMap = [];
                    foreach ($argNodes as $argNode) {
                        $argNodeMap[$argNode->name->value] = $argNodes;
                    }
                    foreach ($fieldDef->args as $argDef) {
                        $argNode = isset($argNodeMap[$argDef->name]) ? $argNodeMap[$argDef->name] : null;
                        if (!$argNode && $argDef->getType() instanceof NonNull) {
                            $context->reportError(new Error(
                                self::missingFieldArgMessage($fieldNode->name->value, $argDef->name, $argDef->getType()),
                                [$fieldNode]
                            ));
                        }
                    }
                }
            ],
            NodeKind::DIRECTIVE => [
                'leave' => function(DirectiveNode $directiveNode) use ($context) {
                    $directiveDef = $context->getDirective();
                    if (!$directiveDef) {
                        return Visitor::skipNode();
                    }
                    $argNodes = $directiveNode->arguments ?: [];
                    $argNodeMap = [];
                    foreach ($argNodes as $argNode) {
                        $argNodeMap[$argNode->name->value] = $argNodes;
                    }

                    foreach ($directiveDef->args as $argDef) {
                        $argNode = isset($argNodeMap[$argDef->name]) ? $argNodeMap[$argDef->name] : null;
                        if (!$argNode && $argDef->getType() instanceof NonNull) {
                            $context->reportError(new Error(
                                self::missingDirectiveArgMessage($directiveNode->name->value, $argDef->name, $argDef->getType()),
                                [$directiveNode]
                            ));
                        }
                    }
                }
            ]
        ];
    }
}
