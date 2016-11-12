<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error\Error;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeType;
use GraphQL\Validator\ValidationContext;

class KnownFragmentNames
{
    static function unknownFragmentMessage($fragName)
    {
        return "Unknown fragment \"$fragName\".";
    }

    public function __invoke(ValidationContext $context)
    {
        return [
            NodeType::FRAGMENT_SPREAD => function(FragmentSpread $node) use ($context) {
                $fragmentName = $node->getName()->getValue();
                $fragment = $context->getFragment($fragmentName);
                if (!$fragment) {
                    $context->reportError(new Error(
                        self::unknownFragmentMessage($fragmentName),
                        [$node->getName()]
                    ));
                }
            }
        ];
    }
}
