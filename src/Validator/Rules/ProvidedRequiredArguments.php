<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Validator\ValidationContext;
use function sprintf;

class ProvidedRequiredArguments extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::FIELD     => [
                'leave' => static function (FieldNode $fieldNode) use ($context) : ?VisitorOperation {
                    $fieldDef = $context->getFieldDef();

                    if (! $fieldDef) {
                        return Visitor::skipNode();
                    }
                    $argNodes = $fieldNode->arguments ?? [];

                    $argNodeMap = [];
                    foreach ($argNodes as $argNode) {
                        $argNodeMap[$argNode->name->value] = $argNode;
                    }
                    foreach ($fieldDef->args as $argDef) {
                        $argNode = $argNodeMap[$argDef->name] ?? null;
                        if ($argNode || (! ($argDef->getType() instanceof NonNull)) || $argDef->defaultValueExists()) {
                            continue;
                        }

                        $context->reportError(new Error(
                            self::missingFieldArgMessage($fieldNode->name->value, $argDef->name, $argDef->getType()),
                            [$fieldNode]
                        ));
                    }

                    return null;
                },
            ],
            NodeKind::DIRECTIVE => [
                'leave' => static function (DirectiveNode $directiveNode) use ($context) : ?VisitorOperation {
                    $directiveDef = $context->getDirective();
                    if (! $directiveDef) {
                        return Visitor::skipNode();
                    }
                    $argNodes   = $directiveNode->arguments ?? [];
                    $argNodeMap = [];
                    foreach ($argNodes as $argNode) {
                        $argNodeMap[$argNode->name->value] = $argNodes;
                    }

                    foreach ($directiveDef->args as $argDef) {
                        $argNode = $argNodeMap[$argDef->name] ?? null;
                        if ($argNode || (! ($argDef->getType() instanceof NonNull)) || $argDef->defaultValueExists()) {
                            continue;
                        }

                        $context->reportError(new Error(
                            self::missingDirectiveArgMessage(
                                $directiveNode->name->value,
                                $argDef->name,
                                $argDef->getType()
                            ),
                            [$directiveNode]
                        ));
                    }

                    return null;
                },
            ],
        ];
    }

    public static function missingFieldArgMessage($fieldName, $argName, $type)
    {
        return sprintf(
            'Field "%s" argument "%s" of type "%s" is required but not provided.',
            $fieldName,
            $argName,
            $type
        );
    }

    public static function missingDirectiveArgMessage($directiveName, $argName, $type)
    {
        return sprintf(
            'Directive "@%s" argument "%s" of type "%s" is required but not provided.',
            $directiveName,
            $argName,
            $type
        );
    }
}
