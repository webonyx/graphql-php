<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\FragmentSpread;
use GraphQL\Language\AST\Node;
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
            Node::FRAGMENT_SPREAD => function(FragmentSpread $node) use ($context) {
                $fragmentName = $node->name->value;
                $fragment = $context->getFragment($fragmentName);
                if (!$fragment) {
                    return new Error(
                        self::unknownFragmentMessage($fragmentName),
                        [$node->name]
                    );
                }
            }
        ];
    }
}
