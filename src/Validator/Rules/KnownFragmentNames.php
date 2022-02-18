<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Validator\QueryValidationContext;

class KnownFragmentNames extends ValidationRule
{
    public function getVisitor(QueryValidationContext $context): array
    {
        return [
            NodeKind::FRAGMENT_SPREAD => static function (FragmentSpreadNode $node) use ($context): void {
                $fragmentName = $node->name->value;
                $fragment = $context->getFragment($fragmentName);
                if ($fragment !== null) {
                    return;
                }

                $context->reportError(new Error(
                    static::unknownFragmentMessage($fragmentName),
                    [$node->name]
                ));
            },
        ];
    }

    public static function unknownFragmentMessage(string $fragName): string
    {
        return "Unknown fragment \"{$fragName}\".";
    }
}
