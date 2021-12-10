<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ValidationContext;

use function array_map;
use function sprintf;

/**
 * Known argument names
 *
 * A GraphQL field is only valid if all supplied arguments are defined by
 * that field.
 */
class KnownArgumentNames extends ValidationRule
{
    public function getVisitor(ValidationContext $context): array
    {
        $knownArgumentNamesOnDirectives = new KnownArgumentNamesOnDirectives();

        return $knownArgumentNamesOnDirectives->getVisitor($context) + [
            NodeKind::ARGUMENT => static function (ArgumentNode $node) use ($context): void {
                $argDef = $context->getArgument();
                if ($argDef !== null) {
                    return;
                }

                $fieldDef = $context->getFieldDef();
                if ($fieldDef === null) {
                    return;
                }

                $parentType = $context->getParentType();
                if (! $parentType instanceof NamedType) {
                    return;
                }

                $context->reportError(new Error(
                    static::unknownArgMessage(
                        $node->name->value,
                        $fieldDef->name,
                        $parentType->name,
                        Utils::suggestionList(
                            $node->name->value,
                            array_map(
                                static fn (FieldArgument $arg): string => $arg->name,
                                $fieldDef->args
                            )
                        )
                    ),
                    [$node]
                ));
            },
        ];
    }

    /**
     * @param array<int, string> $suggestedArgs
     */
    public static function unknownArgMessage(string $argName, string $fieldName, string $typeName, array $suggestedArgs): string
    {
        $message = sprintf('Unknown argument "%s" on field "%s" of type "%s".', $argName, $fieldName, $typeName);
        if (isset($suggestedArgs[0])) {
            $message .= sprintf(' Did you mean %s?', Utils::quotedOrList($suggestedArgs));
        }

        return $message;
    }
}
