<?php
namespace GraphQL\Validator\Rules;


use GraphQL\Error;
use GraphQL\Language\AST\FragmentDefinition;
use GraphQL\Language\AST\InlineFragment;
use GraphQL\Language\AST\Node;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\CompositeType;
use GraphQL\Type\Definition\Type;
use GraphQL\Validator\Messages;
use GraphQL\Validator\ValidationContext;

class FragmentsOnCompositeTypes
{
    static function inlineFragmentOnNonCompositeErrorMessage($type)
    {
        return "Fragment cannot condition on non composite type \"$type\".";
    }

    static function fragmentOnNonCompositeErrorMessage($fragName, $type)
    {
        return "Fragment \"$fragName\" cannot condition on non composite type \"$type\".";
    }

    public function __invoke(ValidationContext $context)
    {
        return [
            Node::INLINE_FRAGMENT => function(InlineFragment $node) use ($context) {
                $type = $context->getType();

                if ($type && !Type::isCompositeType($type)) {
                    return new Error(
                        self::inlineFragmentOnNonCompositeErrorMessage($type),
                        [$node->typeCondition]
                    );
                }
            },
            Node::FRAGMENT_DEFINITION => function(FragmentDefinition $node) use ($context) {
                $type = $context->getType();

                if ($type && !Type::isCompositeType($type)) {
                    return new Error(
                        self::fragmentOnNonCompositeErrorMessage($node->name->value, Printer::doPrint($node->typeCondition)),
                        [$node->typeCondition]
                    );
                }
            }
        ];
    }
}
