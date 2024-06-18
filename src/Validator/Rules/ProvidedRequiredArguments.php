<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use GraphQL\Language\VisitorOperation;
use GraphQL\Validator\QueryValidationContext;

class ProvidedRequiredArguments extends ValidationRule
{
    /** @throws \Exception */
    public function getVisitor(QueryValidationContext $context): array
    {
        $providedRequiredArgumentsOnDirectives = new ProvidedRequiredArgumentsOnDirectives();

        return $providedRequiredArgumentsOnDirectives->getVisitor($context) + [
            NodeKind::FIELD => [
                'leave' => static function (FieldNode $fieldNode) use ($context): ?VisitorOperation {
                    $fieldDef = $context->getFieldDef();

                    if ($fieldDef === null) {
                        return Visitor::skipNode();
                    }

                    $argNodes = $fieldNode->arguments;

                    $argNodeMap = [];
                    foreach ($argNodes as $argNode) {
                        $argNodeMap[$argNode->name->value] = $argNode;
                    }

                    foreach ($fieldDef->args as $argDef) {
                        $argNode = $argNodeMap[$argDef->name] ?? null;
                        if ($argNode === null && $argDef->isRequired()) {
                            $context->reportError(new Error(
                                static::missingFieldArgMessage($fieldNode->name->value, $argDef->name, $argDef->getType()->toString()),
                                [$fieldNode]
                            ));
                        }
                    }

                    return null;
                },
            ],
        ];
    }

    public static function missingFieldArgMessage(string $fieldName, string $argName, string $type): string
    {
        return "Field \"{$fieldName}\" argument \"{$argName}\" of type \"{$type}\" is required but not provided.";
    }
}
