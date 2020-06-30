<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\ValidationContext;
use function sprintf;

class ProvidedRequiredArguments extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        $providedRequiredArgumentsOnDirectives = new ProvidedRequiredArgumentsOnDirectives();

        return $providedRequiredArgumentsOnDirectives->getVisitor($context) + [
            NodeKind::FIELD => [
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
                        if ($argNode || ! $argDef->isRequired()) {
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
}
