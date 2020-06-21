<?php

declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ValidationContext;
use function array_map;
use function count;
use function sprintf;

/**
 * Known argument names
 *
 * A GraphQL field is only valid if all supplied arguments are defined by
 * that field.
 */
class KnownArgumentNames extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        $knownArgumentNamesOnDirectives = new KnownArgumentNamesOnDirectives();

        return $knownArgumentNamesOnDirectives->getVisitor($context) + [
            NodeKind::ARGUMENT => static function (ArgumentNode $node) use ($context) : void {
                $argDef = $context->getArgument();
                if ($argDef !== null) {
                    return;
                }

                $fieldDef   = $context->getFieldDef();
                $parentType = $context->getParentType();
                if ($fieldDef === null || ! ($parentType instanceof Type)) {
                    return;
                }

                $context->reportError(new Error(
                    self::unknownArgMessage(
                        $node->name->value,
                        $fieldDef->name,
                        $parentType->name,
                        Utils::suggestionList(
                            $node->name->value,
                            array_map(
                                static function ($arg) : string {
                                    return $arg->name;
                                },
                                $fieldDef->args
                            )
                        )
                    ),
                    [$node]
                ));

                return;
            },
        ];
    }

    /**
     * @param string[] $suggestedArgs
     */
    public static function unknownArgMessage($argName, $fieldName, $typeName, array $suggestedArgs)
    {
        $message = sprintf('Unknown argument "%s" on field "%s" of type "%s".', $argName, $fieldName, $typeName);
        if (isset($suggestedArgs[0])) {
            $message .= sprintf(' Did you mean %s?', Utils::quotedOrList($suggestedArgs));
        }

        return $message;
    }
}
